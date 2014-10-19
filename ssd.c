/*******************************************************************************

	Copyright 2001, 2004 Wedge Community Co-op
	This file is part of IS4C.

	IS4C is free software; you can redistribute it and/or modify
	it under the terms of the GNU General Public License as published by
	the Free Software Foundation; either version 2 of the License, or
	(at your option) any later version.

	IS4C is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	GNU General Public License for more details.

	You should have received a copy of the GNU General Public License
	in the file license.txt along with IS4C; if not, write to the Free Software
	Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
********************************************************************************

   This is the preliminary daemon that effectively acts as part of the
   driver for the single cable Magellan scanner scale on the Linux platform.
   The settings for the serial port communication parameters are
   based on the factory defaults for Magellan. The polling behaviour,
   likewise is based on the technical specs for the Magellan.
   Details are listed in the document scrs232.doc in the installation directory.

   In brief, what the daemon does is to listen in on the serial port
   for in-coming data. It puts the last scanned input in
   the file "/pos/is4c/rs232/scanner".
   Similiarly it puts the last weight input in the
   file "/pos/is4c/rs232/scale".
   The pages chkscale.php and chkscanner check these
   files and assign their contents to the appropriate global variables.

   To set up the daemon, compile ssd.c and arrange for it
   to run at boot time.
*/

#include <sys/types.h>
#include <sys/stat.h>
#include <stdio.h>
#include <stdlib.h>
#include <stdarg.h>
#include <fcntl.h>
#include <errno.h>
#include <unistd.h> /* direct serial I/O */
#include <syslog.h>
#include <string.h>
#include <termios.h> /* POSIX terminal control definitions */
#include <ctype.h>
#include <signal.h>

int scannerFD = 0;
int bytes_read;
char serialBuffer[512];
int zeroed = 0;
char zeroWeight[8] = "S110000";
char lastWeight[5] = "0000";

struct termios options;

char current_config_file	[1024]	= 	"ssd.conf";
char current_serial_port	[1024]	= 	"/dev/ttyS0";
char current_scanner_file	[1024]	=	"scanner";
char current_scale_file		[1024]	= 	"scale";
char current_pid_file		[1024]	= 	"ssd.pid";
char current_log_file		[1024]	= 	"ssd.log";
int current_log_level				= 	LOG_NOTICE; /* LOG_NOTICE = 5 */
int use_syslog						=	1;
int use_logfile						=	1;
int use_stdout						=	0;

FILE *fp_log;

int q = 0;	// Serial number of scanner/scale read


void log_message(int level, const char* format, ...)
/* Level should be one of:
   LOG_EMERG, LOG_ALERT, LOG_CRIT, LOG_ERR, LOG_WARNING, LOG_NOTICE, LOG_INFO, LOG_DEBUG
*/
{
	va_list ap;
	va_start(ap, format);

	/* for single-character "heartbeat" messages (including a bare newline),
	   we won't append a newline
	*/
	static int last_was_heartbeat = 0;
	int is_heartbeat = (strlen(format) == 1? 1 : 0);

	if (use_syslog && !is_heartbeat) {
		vsyslog(LOG_MAKEPRI(LOG_DAEMON, level), format, ap);
	}
	if (level <= current_log_level) {
		if (use_logfile) {
			if (!fp_log && strlen(current_log_file))
				fp_log = fopen(current_log_file, "a");
			if (!is_heartbeat)
				fprintf(fp_log, "\n");
			vfprintf(fp_log, format, ap);
			fflush(fp_log);
		}
		if (use_stdout) {
			FILE *out = (level < LOG_NOTICE? stderr : stdout);
			if (!is_heartbeat)
				fprintf(out, "\n");
			vfprintf(out, format, ap);
			fflush(out);
		}
	}
	last_was_heartbeat = is_heartbeat;

	va_end(ap);
}


void read_default_config_file(char *loc)
{
	FILE *fp_conf;
	char line[1024];
	char *option;
	char *value;

	if (loc != NULL) strcpy(current_config_file, loc);

	fp_conf = fopen(current_config_file, "r");
	if (fp_conf == NULL) return;

	while (fgets(line, 1024, fp_conf) != NULL) {
		option = strtok(line, " \t");
		value = strtok(NULL, "\n");

		if (strcmp(option, "ConfFile") == 0) {
			strcpy(current_config_file, value);
		}
		else if (strcmp(option, "SerialPort") == 0) {
			strcpy(current_serial_port, value);
		}
		else if (strcmp(option, "ScannerFile") == 0) {
			strcpy(current_scanner_file, value);
		}
		else if (strcmp(option, "ScaleFile") == 0) {
			strcpy(current_scale_file, value);
		}
		else if (strcmp(option, "PIDFile") == 0) {
			strcpy(current_pid_file, value);
		}
		else if (strcmp(option, "LogFile") == 0) {
			strcpy(current_log_file, value);
		}
		else if (strcmp(option, "LogLevel") == 0) {
			/* LogLevel should be one of (per syslog):
				0 = LOG_EMERG
				1 = LOG_ALERT
				2 = LOG_CRIT
				3 = LOG_ERR
				4 = LOG_WARNING
				5 = LOG_NOTICE
				6 = LOG_INFO
				7 = LOG_DEBUG
			*/
			current_log_level = strtoul(value, NULL, 10);
		}
		else if (strcmp(option, "UseSyslog") == 0) {
			use_syslog = (strtoul(value, NULL, 10)? 1 : 0);
		}
	}
	fclose(fp_conf);
	if (fp_log) fclose(fp_log);
}


void write_file(const char *path, const char *mode, const char* format, ...)
{
	va_list ap;
	va_start(ap, format);

	FILE *out = fopen(path, mode);
	if (out == NULL) {
		log_message(LOG_ERR, "Couldn't open file '%s' in mode '%s'.", path, mode);
	}
	else {
		log_message(LOG_INFO, "Writing file '%s' in mode '%s'.", path, mode);
		vfprintf(out, format, ap);
		fclose(out);
	}

	va_end(ap);
}


void set_handler(int signal, void *handler)
{
	struct sigaction new_sa;

	new_sa.sa_handler = handler;
	new_sa.sa_flags = SA_RESTART;
	if (sigaction(signal, &new_sa, 0) != 0) {
		log_message(LOG_ERR, "Couldn't set up handler.");
		exit(EXIT_FAILURE);
	}
}


int connect_scanner(void)
{
	if (scannerFD) return scannerFD;

	scannerFD = open(current_serial_port, O_RDWR | O_NOCTTY | O_NDELAY);
	if (scannerFD == -1) {
		log_message(LOG_ERR, "Unable to open() %s: error %s (%d)\n", current_serial_port, strerror(errno), errno);
	}
	return scannerFD;
}


void write_scanner(const char *msg)
{
	if (!scannerFD) connect_scanner();

	write(scannerFD, msg, strlen(msg));
	write(scannerFD, "\r", 2);
	log_message(LOG_INFO, "We say '%s'.", msg);
}


void start_scanner(void)
{
	write_scanner("S00");    /* Hard reset */
	write_scanner("S01");    /* Enable */
	write_scanner("S334");   /* Good beep tone */
	log_message(LOG_NOTICE, "Scanner started.");
}


void stop_scanner(void)
{
	write_scanner("S335");   /* Power down */
	log_message(LOG_NOTICE, "Scanner stopped.");
}


void handle_HUP(int signo /*, siginfo_t *info, void *context */)
{
	log_message(LOG_NOTICE, "Received SIGHUP - rereading config, restarting scanner.");
	stop_scanner();
	read_default_config_file(NULL);
	start_scanner();
}


void handle_TSTP(int signo /*, siginfo_t *info, void *context */)
{
	log_message(LOG_NOTICE, "Received SIGTSTP - stopping scanner.");
	stop_scanner();
}


void handle_CONT(int signo /*, siginfo_t *info, void *context */)
{
	log_message(LOG_NOTICE, "Received SIGCONT - starting scanner.");
	start_scanner();
}


void handle_XFSZ(int signo /*, siginfo_t *info, void *context */)
{
	log_message(LOG_ERR, "Received XFSZ - truncating log file.");
	write_file(current_log_file, "w", "");
}


int main(int argc, char *argv[])
{
	/* Our process ID and Session ID */
	pid_t pid, sid;

	/* Specify command-line specified config file if possible; otherwise NULL specifies compiled default */
	read_default_config_file(argc > 1? argv[1] : NULL);

	/* Fork off the parent process */
	pid = fork();
	if (pid < 0) {
		log_message(LOG_ERR, "Couldn't fork child process.");
		exit(EXIT_FAILURE);
	}
	/* If we got a good PID, then
	   we can exit the parent process. */
	else if (pid > 0) {
		log_message(LOG_NOTICE, "Forked child with PID %d.", pid);
		exit(EXIT_SUCCESS);
	}


	/* Change the file mode mask */
	umask(0);

	/* Open any logs here */

	/* Log our pid */
	write_file(current_pid_file, "w", "%d", getpid());

  	/* Create a new Session ID for the child process */
	sid = setsid();
	if (sid < 0) {
		log_message(LOG_ERR, "Couldn't create new Session ID.");
		exit(EXIT_FAILURE);
	}
	log_message(LOG_NOTICE, "Created Session ID %d.", sid);

	/* Change the current working directory */
/* 	if ((chdir("/")) < 0) {
 		log_message("Couldn't change directory.\n");
 		exit(EXIT_FAILURE);
 	}
*/
	/* Set up signal handlers */
	set_handler(SIGHUP, handle_HUP);
	set_handler(SIGTSTP, handle_TSTP);
	set_handler(SIGCONT, handle_CONT);
	set_handler(SIGXFSZ, handle_XFSZ);

	/* Close out the standard file descriptors */
	close(STDIN_FILENO);
	close(STDOUT_FILENO);
	close(STDERR_FILENO);

	log_message(LOG_NOTICE, "Starting up scanner/scale connection.");
	connect_scanner();
	start_scanner();

	/* Configure port reading */
	/*fcntl(scannerFD, F_SETFL, FNDELAY);  */

	/* Get the current options for the port */
	tcgetattr(scannerFD, &options);

	/* Set the baud rates to 9600 */
	cfsetispeed(&options, B9600);

	/* Enable the receiver and set local mode */
	options.c_cflag |= (CLOCAL | CREAD);

	/* 7 bits, odd parity */
	options.c_cflag |= PARENB;	/* enable parity */
	options.c_cflag |= PARODD;  /* odd parity */
	options.c_cflag &= ~CSTOPB;	/* disable double stop */
	options.c_cflag &= ~CSIZE;
	options.c_cflag |= CS7;
	options.c_cflag |= CRTSCTS;

	/* Enable data to be processed as raw input */
	options.c_lflag &= ~(ICANON | ECHO | ISIG);

	/* Set the new options for the port */
/*  tcsetattr(scannerFD, TCSANOW, &options);  */

	log_message(LOG_NOTICE, "Activating scale.");
	write_scanner("S14");    /* Scale Monitor */

	while (1) {
		bytes_read = read(scannerFD, serialBuffer, 512);
		if (bytes_read == -1) {
			switch (errno) {
				case EAGAIN: /* we expect this whenever nothing's happening */
					break;
				default:
					write_file(current_log_file, "a", "(%d)", errno);
					log_message(LOG_ERR, "Unable to read() %s: error %s (%d)\n", current_serial_port, strerror(errno), errno);
			}
		}
		else if (bytes_read == 0) {
			log_message(LOG_DEBUG, ".");
		}
		else if (bytes_read > 0) {

			/* Received message */
			strtok(serialBuffer, "\n");
			log_message(LOG_DEBUG, "Magellan says '%s'.", serialBuffer);

			/**************** Process scanned data ****************/
			if (strncmp(serialBuffer, "S0", 2) == 0) {
				log_message(LOG_NOTICE, "%d Scanned: %s", q++, serialBuffer+4);
				write_file(current_scanner_file, "w", "%s\n", serialBuffer+4);
			}

			/**************** Process weight data ******************/
			else if (strncmp(serialBuffer, "S11", 3) == 0) {
				log_message(LOG_INFO, "Scale at stable non-zero weight (request).");
				zeroed = 0;
				if (strncmp(serialBuffer+3, lastWeight, 5) != 0) {
					log_message(LOG_NOTICE, "%d Weighed (per request): %s (previous was %s)", q++, serialBuffer+3, lastWeight);
					write_file(current_scale_file, "a", "%s\n", serialBuffer);
					strcpy(lastWeight, serialBuffer+3);
				}
			}
			else if (strncmp(serialBuffer, "S140", 4) == 0) {
				log_message(LOG_NOTICE, "Scale not ready.");
				zeroed = 0;
			}
			else if (strncmp(serialBuffer, "S141", 4) == 0) {
				/* scale unstable */
				log_message(LOG_INFO, "~");
				zeroed = 0;
			}
			else if (strncmp(serialBuffer, "S142", 4) == 0) {
				log_message(LOG_NOTICE, "Scale over capacity.");
				zeroed = 0;
			}
			else if (strncmp(serialBuffer, "S143", 4) == 0) {
				if (!zeroed) {
					log_message(LOG_INFO, "Scale at stable zero weight.");
					write_file(current_scale_file, "w", zeroWeight);
					strcpy(lastWeight, "0000");
					zeroed = 1;
					write_scanner("S11");    /* Scale Valid Weight Request */
				}
			}
			else if (strncmp(serialBuffer, "S144", 4) == 0) {
				zeroed = 0;
				if (strncmp(serialBuffer+4, lastWeight, 5) != 0) {
					log_message(LOG_INFO, "Scale at stable non-zero weight (monitor).");
					log_message(LOG_NOTICE, "%d Weighed (per monitor): %s (previous was %s)", q++, serialBuffer+4, lastWeight);
					write_file(current_scale_file, "w", "%s\n", serialBuffer);
					strcpy(lastWeight, serialBuffer+4);
				}
				else {
					/* continuing stable non-zero weight */
					log_message(LOG_INFO, "#");
				}
			}
			else if (strncmp(serialBuffer, "S145", 4) == 0) {
				log_message(LOG_INFO, "Scale is under zero.");
				zeroed = 0;
			}

		}	/* End non-empty buffer data processing */

		/* If scale was zeroed there's an S11 request pending; relax. */
		if (zeroed) {
			usleep(10000);   /* 10 ms */
		}
		/* Otherwise, wait a bit longer and explicitly retrigger S14. */
		else {
			log_message(LOG_DEBUG, "=");
			usleep(100000);   /* 100 ms */
			write_scanner("S14");    /* Scale Monitor */
		}
	}

	close(scannerFD);
	exit(EXIT_SUCCESS);
}
