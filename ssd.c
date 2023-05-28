#include <ctype.h>
#include <errno.h>
#include <fcntl.h>
#include <linux/kd.h>
#include <poll.h>
#include <signal.h>
#include <stdarg.h>
#include <stdio.h>
#include <stdlib.h>
#include <string.h>
#include <sys/ioctl.h>
#include <sys/stat.h>
#include <sys/time.h>
#include <sys/types.h>
#include <syslog.h>
#include <termios.h> // POSIX terminal control definitions
#include <time.h>
#include <unistd.h> // direct serial I/O


// config settings with their defaults
char config_filepath  [1024]	= "ssd.conf";
char serial_port_path [1024]	= "/dev/ttyS0";
int serial_port_baud            = 9600; // 2400|4800|9600|19200
int serial_port_data_bits       = 7; // 5|6|7|8
int serial_port_double_stop     = 0; // 0|1
char serial_port_parity         = 'o'; // n|o|e (none/odd/even)
int serial_port_flow_control	= 'h'; // n|h|s (none/hardware/software)
char scanner_filepath [1024]	= "scanner";
char scale_filepath   [1024]	= "scale";
char pid_filepath     [1024]	= "";
char log_filepath     [1024]	= "";
int use_logfile                 = 0; // 0|1
int use_stdout                  = 1; // 0|1 (daemon/shell program)
int use_syslog                  = 0; // 0|1
int log_level                   = 5; // 0-8 (5=LOG_NOTICE)

volatile sig_atomic_t mode;
volatile sig_atomic_t signalled[64];
time_t last_device_response;

FILE *logFP;
int scannerFD = 0;
int bytes_read;
char serialBuffer[512];
char zeroWeight[8] = "S110000";
char lastWeight[5] = "0000";

enum mode_enum { STARTUP, READY, WEIGHING, FAULT, SLEEP, SUSPEND, SHUTDOWN };
char *mode_name[] = { "Startup", "Ready", "Weighing", "Fault", "Sleep", "Suspend", "Shutdown" };

int log_message(int level, const char* format, ...)
// level is one of LOG_EMERG, LOG_ALERT, LOG_CRIT, LOG_ERR, LOG_WARNING, LOG_NOTICE, LOG_INFO, LOG_DEBUG
{
	va_list ap;
	va_start(ap, format);

	static struct timeval last_log_time;
	static int last_log_day;

	static int prior_heartbeats = 0;
	int is_heartbeat = (strlen(format) == 1);
	int output_was_generated = 0;

	if (use_syslog && !is_heartbeat) {
		vsyslog(LOG_MAKEPRI(LOG_DAEMON, level), format, ap);
	}

	if (level <= log_level) {
		output_was_generated = 1;

		// prepend newline, except for consecutive single-char "heartbeat" messages
		int prepend_newline = !(prior_heartbeats && is_heartbeat);

		struct timeval tv;
		struct timezone tz;
		struct tm *now;
		char time_tag[50];

		gettimeofday(&tv, &tz);
		now = localtime(&tv.tv_sec);
		struct timeval since_last_log;
		timersub(&tv, &last_log_time, &since_last_log);

		if (now->tm_mday != last_log_day || since_last_log.tv_sec > 300) {
			strftime(time_tag, 50, "\n====== %a %b %e %Y %X ======\n", now);
			last_log_day = now->tm_mday;
			last_log_time.tv_sec = tv.tv_sec;
			last_log_time.tv_usec = tv.tv_usec;
		}
		else if (since_last_log.tv_sec > (is_heartbeat? 60 : 0)) {
			strftime(time_tag, 50, "\n--- %X ---\n", now);
			last_log_time.tv_sec = tv.tv_sec;
			last_log_time.tv_usec = tv.tv_usec;
		}
		else {
			strcpy(time_tag, "");
// 			sprintf(time_tag, "\n[%d / %d]\n", (int)since_last_log.tv_sec, (int)since_last_log.tv_usec);
		}

		if (use_stdout) {
			FILE *out = (level < LOG_NOTICE? stderr : stdout);
			if (prepend_newline)
				fprintf(out, "%s", "\n");
			if (!is_heartbeat)
				fprintf(out, "%s", time_tag);
			vfprintf(out, format, ap);
			fflush(out);
		}
		if (use_logfile) {
			if (!logFP && strlen(log_filepath)) {
				logFP = fopen(log_filepath, "w");
				if (!logFP) {
					fprintf(stderr, "Couldn't open logfile '%s': error %s (%d)", log_filepath, strerror(errno), errno);
					syslog(LOG_MAKEPRI(LOG_DAEMON, LOG_ERR), "Couldn't open logfile '%s': error %s (%d)", log_filepath, strerror(errno), errno);
				}
			}
			if (logFP) {
				if (prepend_newline)
					fprintf(logFP, "\n");
				if (!is_heartbeat)
					fprintf(logFP, "%s", time_tag);
				vfprintf(logFP, format, ap);
				fflush(logFP);
			}
		}

		if (is_heartbeat) prior_heartbeats++;
		if (!is_heartbeat || prior_heartbeats > 60) prior_heartbeats = 0;
	}

	va_end(ap);
	return output_was_generated;
}


int param_change(char *name, char *type, void *old_value, void *new_value, int also_stdout)
{
// also_stdout=1;
	int is_changed;
// printf("param_change(\"%s\", \"%s\", \"%s\", \"%s\", %d)...\n", name, type, (char *)old_value, (char *)new_value, also_stdout);
	if (strcmp(type, "string") == 0) {
		is_changed = strcmp((char *)old_value, (char *)new_value);
		if (is_changed) {
			if (also_stdout) printf("\t%s is specified as '%s' (was '%s')\n", name, (char *)new_value, (char *)old_value);
			log_message(LOG_DEBUG, "%s is specified as '%s' (was '%s')", name, (char *)new_value, (char *)old_value);
			strcpy(old_value, new_value);
		}
		else {
			if (also_stdout) printf("\t%s is specified as '%s' (no change)\n", name, (char *)new_value);
			log_message(LOG_DEBUG, "%s is specified as '%s' (no change)", name, (char *)new_value);
		}
	}
	else if (strcmp(type, "int") == 0 || strcmp(type, "bool") == 0) {
		int new_value_int = (int)strtoul((char *)new_value, NULL, 10);
		is_changed = ((*((int *)old_value)) != new_value_int);
		if (is_changed) {
			if (also_stdout) printf("\t%s is specified as %d (was %d)\n", name, new_value_int, *(int *)old_value);
			log_message(LOG_DEBUG, "%s is specified as %d (was %d)", name, new_value_int, *(int *)old_value);
			*(int *)old_value = new_value_int;
		}
		else {
			if (also_stdout) printf("\t%s is specified as %d (no change)\n", name, new_value_int);
			log_message(LOG_DEBUG, "%s is specified as %d (no change)", name, new_value_int);
		}
	}

	return is_changed;
}


void read_config_file(char *loc)
{
	FILE *config_fp;
	char line[1024];
	char *option;
	char *value;

	int log_filepath_changed = 0;

	int backup_stdout = (1 || !use_stdout && !use_syslog && (!use_logfile || !strlen(log_filepath)));

	if (loc != NULL) {
		strcpy(config_filepath, loc);
	}
	if (backup_stdout) printf("Reading config file '%s':\n", config_filepath);
	log_message(LOG_DEBUG, "Reading config file '%s':", config_filepath);

	if (!config_filepath || !strlen(config_filepath)) return;

	config_fp = fopen(config_filepath, "r");
	if (config_fp == NULL) {
		if (backup_stdout) printf("Couldn't open config file '%s': error %s (%d)\n", config_filepath, strerror(errno), errno);
		log_message(LOG_ERR, "Couldn't open config file '%s': error %s (%d)", config_filepath, strerror(errno), errno);
		return;
	}

	while (fgets(line, 1024, config_fp) != NULL) {
		option = strtok(line, " \t");
		value = strtok(NULL, "\n");

		if (strcmp(option, "ConfFile") == 0) {
			param_change("ConfFile", "string", config_filepath, value, backup_stdout);
		}
		else if (strcmp(option, "SerialPort") == 0) {
			param_change("SerialPort", "string", serial_port_path, value, backup_stdout);
		}
		else if (strcmp(option, "SerialBaud") == 0) {
			param_change("SerialBaud", "int", &serial_port_baud, value, backup_stdout);
		}
		else if (strcmp(option, "SerialDataBits") == 0) {
			param_change("SerialDataBits", "int", &serial_port_data_bits, value, backup_stdout);
		}
		else if (strcmp(option, "SerialParity") == 0) {
			param_change("SerialParity", "string", &serial_port_parity, value, backup_stdout);
		}
		else if (strcmp(option, "SerialDoubleStop") == 0) {
			param_change("SerialDoubleStop", "bool", &serial_port_double_stop, value, backup_stdout);
		}
		else if (strcmp(option, "SerialFlowControl") == 0) {
			param_change("SerialFlowControl", "string", &serial_port_flow_control, value, backup_stdout);
		}
		else if (strcmp(option, "ScannerFile") == 0) {
			param_change("ScannerFile", "string", scanner_filepath, value, backup_stdout);
		}
		else if (strcmp(option, "ScaleFile") == 0) {
			param_change("ScaleFile", "string", scale_filepath, value, backup_stdout);
		}
		else if (strcmp(option, "PIDFile") == 0) {
			param_change("PIDFile", "string", pid_filepath, value, backup_stdout);
		}
		else if (strcmp(option, "LogFile") == 0) {
			if (param_change("LogFile", "string", log_filepath, value, backup_stdout)) {
				int use_logfile_now = strlen(value)? 1 : 0;
				if (backup_stdout) printf("\t\t(also changing UseLogFile from %d to %d; this can be overridden by a later UseLogFile entry)\n", use_logfile, use_logfile_now);
				log_message(LOG_DEBUG, "\t(also changing UseLogFile from %d to %d; this can be overridden by a later UseLogFile entry)", use_logfile, use_logfile_now);
				use_logfile = use_logfile_now;
			}
		}
		else if (strcmp(option, "UseLogFile") == 0) {
			param_change("UseLogFile", "bool", &use_logfile, value, backup_stdout);
		}
		else if (strcmp(option, "UseStdout") == 0) {
			param_change("UseStdout", "bool", &use_stdout, value, backup_stdout);
		}
		else if (strcmp(option, "UseSyslog") == 0) {
			param_change("UseSyslog", "bool", &use_syslog, value, backup_stdout);
		}
		else if (strcmp(option, "LogLevel") == 0) {
			// should be one of LOG_EMERG, LOG_ALERT, LOG_CRIT, LOG_ERR, LOG_WARNING, LOG_NOTICE, LOG_INFO, LOG_DEBUG
			param_change("LogLevel", "int", &log_level, value, backup_stdout);
		}
	}
	fclose(config_fp);

	if (backup_stdout) printf("Done reading config file\n\n");
	log_message(LOG_DEBUG, "Done reading config file\n");

	if (log_filepath_changed && logFP) {
		fclose(logFP);
		logFP = NULL;
	}
}


void write_file(const char *path, const char* format, ...)
{
	va_list ap;
	va_start(ap, format);

	if (path && strlen(path)) {
		FILE *out = fopen(path, "w");
		if (out == NULL) {
			log_message(LOG_ERR, "Couldn't open file '%s' for writing: error %s (%d)", path, mode, strerror(errno), errno);
		}
		else {
			log_message(LOG_DEBUG, "Writing file '%s'.", path);
			vfprintf(out, format, ap);
			fclose(out);
	// 		log_message(LOG_INFO, format, ap);
		}
	}

	va_end(ap);
}


void set_handler(int signal, void *handler)
{
	struct sigaction new_sa;

	new_sa.sa_handler = handler;
	new_sa.sa_flags = SA_RESTART;
	if (sigaction(signal, &new_sa, 0) != 0) {
		log_message(LOG_ERR, "Couldn't set up signal handler.");
		exit(EXIT_FAILURE);
	}
}


int check_speaker(void)
{
	FILE *modules_fp = fopen("/proc/modules", "r");
	if (modules_fp == NULL) {
		log_message(LOG_ERR, "Couldn't read kernel module list: error %s (%d)", strerror(errno), errno);
		return 0;
	}

	char line[1024];
	int speakerFound = 0;
	while (fgets(line, 1024, modules_fp) != NULL) {
		if (strstr(line, "pcspkr") && strstr(line, "Live")) {
			speakerFound = 1;
			break;
		}
	}
	if (speakerFound)
		log_message(LOG_NOTICE, "Speaker module is loaded: %s", strtok(line, "\n"));
	else
		log_message(LOG_WARNING, "No kernel sound module was found; we'll be unable to beep when scanner/scale communications errors are detected.\n\tEdit /etc/modprobe.d/blacklist.conf and/or run modprobe pcspkr to correct this!");

	fclose(modules_fp);
}


void beep(float hz, int ms)
{
	int speaker_fd = open("/dev/console", O_WRONLY);
	if (speaker_fd < 0) {
		log_message(LOG_ERR, "Unable to open() '/dev/console' for speaker connection: %s (%d)", strerror(errno), errno);
		printf("\a"); // last ditch attempt
		fflush(stdout);
		return;
	}

	int ioctl_result = ioctl(speaker_fd, KDMKTONE, (ms << 16) | (int)(1193180 / hz));
	if (ioctl_result)
		log_message(LOG_ERR, "Unable to activate speaker; ioctl() returned %d: %s (%d)", ioctl_result, strerror(errno), errno);

	if (close(speaker_fd) < 0)
		log_message(LOG_ERR, "Unable to close() speaker connection: %s (%d)", strerror(errno), errno);
}


int connect_scanner(void)
{
	struct termios tty;
	int result;

	if (scannerFD) return scannerFD;

	log_message(LOG_NOTICE, "Connecting to '%s'", serial_port_path);

	scannerFD = open(serial_port_path, O_RDWR | O_NOCTTY);
	if (scannerFD < 0) {
		log_message(LOG_ERR, "Unable to open() '%s': error %s (%d)", serial_port_path, strerror(errno), errno);
		return 0;
	}
	if (!isatty(scannerFD)) {
		log_message(LOG_ERR, "'%s' is not a TTY: error %s (%d)", serial_port_path, strerror(errno), errno);
		return 0;
	}

	// thanks https://www.gnu.org/software/libc/manual/html_node/Setting-Modes.html
	result = tcgetattr(scannerFD, &tty);
	if (result < 0) {
		log_message(LOG_ERR, "Unable to tcgetattr() %s: error %s (%d)", serial_port_path, strerror(errno), errno);
		return 0;
	}

	log_message(LOG_DEBUG, "\tinput mode (c_iflag): %d", tty.c_iflag);
	log_message(LOG_DEBUG, "\toutput mode (c_oflag): %d", tty.c_oflag);
	log_message(LOG_DEBUG, "\tcontrol mode (c_cflag): %d", tty.c_cflag);
	log_message(LOG_DEBUG, "\tlocal mode (c_lflag): %d", tty.c_lflag);

	switch (serial_port_baud) {
		case 2400: cfsetspeed(&tty, B2400); break;
		case 4800: cfsetspeed(&tty, B4800); break;
		case 9600: cfsetspeed(&tty, B9600); break;
		case 19200: cfsetspeed(&tty, B19200); break;
		default: log_message(LOG_WARNING, "Ignoring unrecognized %d baud rate request", serial_port_baud);
	}
	switch (serial_port_data_bits) {
		case 5: tty.c_cflag = tty.c_cflag & ~CSIZE | CS5; break;
		case 6: tty.c_cflag = tty.c_cflag & ~CSIZE | CS6; break;
		case 7: tty.c_cflag = tty.c_cflag & ~CSIZE | CS7; break;
		case 8: tty.c_cflag = tty.c_cflag & ~CSIZE | CS8; break;
	}
	switch (serial_port_double_stop) {
		case 0: tty.c_cflag &= ~CSTOPB; break;
		case 1: tty.c_cflag |= CSTOPB; break;
	}
	switch (serial_port_parity) {
		case 'n': tty.c_cflag = tty.c_cflag & ~PARENB; break;
		case 'o': tty.c_cflag = tty.c_cflag | PARENB | PARODD; break;
		case 'e': tty.c_cflag = tty.c_cflag | PARENB & ~PARODD; break;
	}
	switch (serial_port_flow_control) {
		case 'n': tty.c_cflag &= ~CRTSCTS; tty.c_cflag &= ~IXON; break;
		case 'h': tty.c_cflag &= ~IXON; tty.c_cflag |= CRTSCTS; break;
		case 's': tty.c_cflag &= ~CRTSCTS; tty.c_cflag |= IXON; break;
	}
	log_message(LOG_DEBUG, "Setting control mode (c_cflag) to: %d", tty.c_cflag);

	tty.c_lflag |= ICANON; // canonical input (only accept full lines terminated by CR/LF)
	log_message(LOG_DEBUG, "Setting local mode (c_lflag) to: %d", tty.c_lflag);

	result = tcsetattr(scannerFD, TCSANOW, &tty);
	if (result < 0) {
		log_message(LOG_ERR, "Unable to tcsetattr() %s: error %s (%d)", serial_port_path, strerror(errno), errno);
		return 0;
	}
	return scannerFD;
}


void write_serial(const char *msg)
{
	if (!scannerFD) connect_scanner();

	log_message(LOG_DEBUG, "\t-> %s", msg);

	int bytes_written = write(scannerFD, msg, strlen(msg));
	if (bytes_written <= 0) {
		log_message(LOG_ERR, "Unable to write() '%s' to scanner/scale: error %s (%d)", msg, strerror(errno), errno);
	}

	bytes_written = write(scannerFD, "\r", 1);
	if (bytes_written <= 0) {
		log_message(LOG_ERR, "Unable to write() newline to scanner/scale: error %s (%d)", strerror(errno), errno);
	}
}


void start_scanner(void)
{
	mode = STARTUP;
	last_device_response = time(NULL); // start the fault timer

	write_serial("S00"); // hard reset
	write_serial("S01"); // scanner enable
	write_serial("S11"); // scale valid weight request: scale will send us "S11abcd" as soon as a nonzero weight stabilizes
	write_serial("S14"); // scale monitor

	log_message(LOG_NOTICE, "Scanner initialized.");
}


void stop_scanner(void)
{
	write_serial("S12"); // cancel pending weight request; expect S10 reply
	write_serial("S02"); // disable scanner - no indication (laser off, un-wakeable); expect S00 reply; side effect might be spin-up?
	write_serial("S335"); // soft power down (spin down); no reply
}


void disconnect_scanner(void)
{
	if (!scannerFD) return;

	stop_scanner();
	usleep(25000); // 25ms; make sure prior messages deliver in case of subsequent disconnect

	// tcflush() prevents us from hanging if scanner/scale is off or disconnected
	log_message(LOG_DEBUG, "Flushing scanner/scale input/output buffers.");
	tcflush(scannerFD, TCIOFLUSH);

	log_message(LOG_DEBUG, "Closing scanner/scale connection.");
	int closed = close(scannerFD);
	if (closed < 0) {
		log_message(LOG_ERR, "Unable to close() scanner/scale connection on %s: error %s (%d)", serial_port_path, strerror(errno), errno);
	}
	else {
		log_message(LOG_NOTICE, "Scanner/scale connection closed.");
	}

	scannerFD = 0;
}


void show_signalled(int level, char *prepend)
{
	char state[64] = "";
	char sig[40] = "";
	for (int signo = 0; signo < 64; signo++) {
		if (signalled[signo]) {
			sprintf(sig, " (%d = %s)", signo, signo? strsignal(signo) : "any");
			strcat(state, sig);
		}
	}
	if (!strlen(state))
		strcpy(state, " (no signals reported)");
	log_message(level, "%s signals:%s", prepend, state);
}


void process_HUP(int signo)
{
	if (use_stdout) { // shell program
		log_message(LOG_WARNING, "Processing SIGHUP in shell program mode: exiting gracefully.");
		disconnect_scanner();
	}
	else {
		log_message(LOG_WARNING, "Processing SIGHUP in daemon mode: rereading config, restarting scanner.");
		disconnect_scanner();
		read_config_file(NULL);
		start_scanner();
	}
}


void process_TSTP(int signo)
{
	log_message(LOG_WARNING, "Processing SIGTSTP: disconnecting scanner.");
	show_signalled(LOG_DEBUG + 1, "process_TSTP init");
	mode = SUSPEND;
	disconnect_scanner();

	show_signalled(LOG_DEBUG + 1, "process_TSTP self-STOP");
    kill(getpid(), SIGSTOP); // actually suspend ourselves, as originally requested

// 	show_signalled(LOG_DEBUG, "process_TSTP post-STOP");
// 	signalled[0] = signalled[SIGCONT] = 1; // make sure SIGCONT handler gets triggered
	show_signalled(LOG_DEBUG + 1, "process_TSTP exit");
}


void process_CONT(int signo)
{
	log_message(LOG_WARNING, "Processing SIGCONT: starting scanner.");
	start_scanner();
}


void process_XFSZ(int signo)
{
	log_message(LOG_WARNING, "Processing SIGXFSZ: truncating log file.");
	write_file(log_filepath, "");
}


void process_TERM(int signo)
{
	log_message(LOG_WARNING, "Processing %s: shutting down scanner and exiting.", signo == SIGTERM? "SIGTERM" : "SIGINT");
	mode = SHUTDOWN;
	disconnect_scanner();
	exit(EXIT_SUCCESS); /// not sure why daemon received subsequent SIGCONT and restarts, but let's nip this in the bud right here
}



void handler(int signo)
{
	signalled[0] = 1;
	signalled[signo] = 1;
}


int main(int argc, char *argv[])
{
	if (argc == 1) {
		read_config_file("ssd.conf");
	}
	else {
		errno = 0;
		int monitor_requested = strtoul(argv[1], NULL, 10);
		if (errno) { // non-integer argument; hopefully it's a config file name
			read_config_file(argv[1]);
		}
		else {
			// integer argument; enter live monitor (non-daemon) mode at indicated log level
			printf("Live monitor mode: log level %d\n", monitor_requested);
			strcpy(config_filepath, "");
			strcpy(scanner_filepath, "");
			strcpy(scale_filepath, "");
			strcpy(pid_filepath, "");
			strcpy(log_filepath, "");
			log_level = monitor_requested;
			use_stdout = 1;
			use_syslog = 0;
			use_logfile = 0;
		}
	}

	// for safety's sake, dump settings to stdout if both logs are disabled
	if (!use_syslog && (!use_logfile || !strlen(log_filepath))) {
		printf("\n");
		printf("ConfFile: %s\n", config_filepath);
		printf("SerialPort: %s\n", serial_port_path);
		printf("SerialBaud: %d\n", serial_port_baud);
		printf("SerialDataBits: %d\n", serial_port_data_bits);
		printf("SerialDoubleStop: %d\n", serial_port_double_stop);
		printf("SerialParity: %c\n", serial_port_parity);
		printf("SerialFlowControl: %c\n", serial_port_flow_control);
		printf("ScannerFile: %s\n", scanner_filepath);
		printf("ScaleFile: %s\n", scale_filepath);
		printf("PIDFile: %s\n", pid_filepath);
		printf("LogFile: %s\n", log_filepath);
		printf("LogLevel: %d\n", log_level);
		printf("UseStdout: %d\n", use_stdout);
		printf("UseSyslog: %d\n", use_syslog);
		printf("UseLogFile: %d\n", use_logfile);
	}

	// if not using stdout, close out standard file descriptors
	if (!use_stdout) {
		close(STDIN_FILENO);
		close(STDOUT_FILENO);
		close(STDERR_FILENO);
	}

	// make output files non-world-writeable
	umask(S_IWOTH);

	// fork if there's a place for us to leave a breadcrumb
	if (pid_filepath && strlen(pid_filepath)) {
		log_message(LOG_NOTICE, "Forking child process.");

		pid_t pid, sid;

		pid = fork();
		if (pid < 0) {
			log_message(LOG_ERR, "Parent couldn't fork child process.");
			exit(EXIT_FAILURE);
		}
		// if we got a good PID, exit parent process
		else if (pid > 0) {
			log_message(LOG_NOTICE, "Parent forked child with PID %d.", pid);
			exit(EXIT_SUCCESS);
		}
		log_message(LOG_NOTICE, "Child’s forked pid is %d.", getpid());
		write_file(pid_filepath, "%d", getpid());

		// create new Session ID for child process
		sid = setsid();
		if (sid < 0) {
			log_message(LOG_ERR, "Couldn't create new Session ID.");
			exit(EXIT_FAILURE);
		}
		log_message(LOG_NOTICE, "Created Session ID %d.", sid);
	}
	else {
		log_message(LOG_NOTICE, "Not forking; our pid is %d.", getpid());
	}

	// clear signal flags, set up signal handlers
	memset((void *)signalled, 0, 64);

	set_handler(SIGHUP, handler);
	set_handler(SIGTSTP, handler);
	set_handler(SIGCONT, handler);
	set_handler(SIGXFSZ, handler);
	set_handler(SIGINT, handler); // Ctrl-C
	set_handler(SIGTERM, handler);

	check_speaker();

	log_message(LOG_NOTICE, "Clearing scanner/scale files.");
	write_file(scanner_filepath, "");
	write_file(scale_filepath, "");

	log_message(LOG_NOTICE, "Starting up scanner/scale connection.");
	connect_scanner();
	start_scanner();

	// set up pollfd structure for serial port
	struct pollfd fds[1];
	fds[0].fd = scannerFD;
	fds[0].events = POLLIN | POLLPRI;

	// enter the daemon loop
	while (mode != SHUTDOWN) {

		// *************** First handle signals! ***************
		if (signalled[0]) {
			log_message(LOG_INFO, "Signal(s) reported; processing now.");
			show_signalled(LOG_DEBUG, "Main signal loop");
			signalled[0] = 0;
			for (int signo = 1; signo < 64; signo++) {
				if (signalled[signo]) {
					signalled[signo] = 0;
					switch (signo) {
						case SIGHUP: process_HUP(signo); break; // shell exit OR (if daemon mode) request to reread config
						case SIGTSTP: process_TSTP(signo); break; // sent by Ctrl-Z
						case SIGCONT: process_CONT(signo); break; // sent by shell "fg" command
						case SIGXFSZ: process_XFSZ(signo); break;
						case SIGINT: process_TERM(signo); break; // sent by Ctrl-C
						case SIGTERM: process_TERM(signo); break; // sent by system shutdown
						default:
							if (strsignal(signo))
								log_message(LOG_NOTICE, "Received unhandled signal %d (%s)", signo, strsignal(signo));
							else
								log_message(LOG_ERR, "Received undefined signal %d", signo);
					}
				}
			}
			continue;
		} // if (signalled[0])

		log_message(LOG_DEBUG + 1, "%d: %s", mode, mode_name[mode]);

		// *************** Alert on faults! ***************
		switch (mode) {
			case READY:
			case WEIGHING:
				if (time(NULL) - last_device_response > 3) {
					log_message(LOG_NOTICE, "Scanner/scale has gone offline while in %s state; beginning reconnect attempts.", mode_name[mode]);
					beep(800, 3000); // 3-second beep
					mode = FAULT;
					write_serial("S14"); // Scale Monitor
				}
				break;
			case STARTUP:
				if (time(NULL) - last_device_response > 6) {
					log_message(LOG_NOTICE, "Scanner/scale has gone offline while in %s state; beginning reconnect attempts.", mode_name[mode]);
					beep(800, 3000); // 3-second beep
					mode = FAULT;
					write_serial("S14"); // Scale Monitor
				}
				break;
			case FAULT:
				write_serial("S14"); // Scale Monitor
			case SLEEP: ; // let us sleep (not yet implemented)
			case SUSPEND: ; // let us hibernate
			case SHUTDOWN: ; // let us die
		}

		// *************** Await message (with timeout) ***************
		log_message(LOG_DEBUG + 1, "poll(): %dms", (mode == READY? 2500 : mode == WEIGHING? 50 : 1000) /* timeout in ms */);
		int ret = poll(fds, 1 /* number of file descriptors */, (mode == READY? 2500 : mode == WEIGHING? 50 : 1000) /* timeout in ms */);
		if (ret < 0) {
			if (errno == EINTR) {
				// at least one signal received; will be handled on next loop pass
				log_message(LOG_INFO, "Signal(s) interrupted our device read!");
				continue;
			}
			else {
				log_message(LOG_ERR, "During poll(): error %s (%d)", strerror(errno), errno);
				exit(EXIT_FAILURE);
			}
		}
		if (ret == 0) { // timed out with no input OR signals
			log_message(LOG_DEBUG + 1, "Timout on poll() with no scanner/scale OR signals received.")
				|| log_message(LOG_DEBUG, ".");
			write_serial("S14"); // Scale Monitor; just to make sure scale is alive and well
			continue;
		}

		// check if serial port has data to read -- check in prior block means this block may never execute! ///
		if (!(fds[0].revents & (POLLIN | POLLPRI))) {
			log_message(LOG_DEBUG + 1, "No POLLIN or POLLPRI updates seen from poll().")
				|| log_message(LOG_DEBUG, "_");
			write_serial("S14"); // Scale Monitor; just to make sure scale is alive and well
			continue;
		}

		// *************** Check message ***************
		bytes_read = read(scannerFD, serialBuffer, 512);
		if (bytes_read < 0) {
			if (errno != EAGAIN) { // we expect EAGAIN whenever nothing's happening
				log_message(LOG_ERR, "Unable to read() %s: error %s (%d)", serial_port_path, strerror(errno), errno);
			}
			continue;
		}
		else if (bytes_read == 0) {
			log_message(LOG_WARNING, "Unable expected 0-byte read() on %s: error %s (%d)", serial_port_path, strerror(errno), errno);
			continue;
		}

		// *************** Note message ***************
		last_device_response = time(NULL);
		if (mode == FAULT) {
			log_message(LOG_NOTICE, "Scanner/scale is back online.");
			mode = READY;
			beep(1000, 200); // emulate the scanner/scale's own beep pitch
			write_serial("S334"); // good beep tone
			write_serial("S01"); // scanner enable
			write_serial("S11"); // scale valid weight request: scale will send us "S11abcd" as soon as a nonzero weight stabilizes
		}

		// *************** Process message ***************
		strtok(serialBuffer, "\n");
		log_message(LOG_DEBUG, "\t\t\tsaw %s", serialBuffer);

		// *************** Process scanner data ***************
		if (strncmp(serialBuffer, "S08", 3) == 0) {
			log_message(LOG_NOTICE, "Scanned: %s", serialBuffer+4);
			write_file(scanner_filepath, serialBuffer+4);
		}
		else if (strncmp(serialBuffer, "S03", 3) == 0) {
			// S03 Scanner Status response: S0301x0\n where x = 0 if scanner disabled, 1 otherwise
			// S04 Scanner Switch Read response: S03010000102
			switch (strlen(serialBuffer)) {
				case 7:
					log_message(LOG_INFO, "Scanner Status response: %s", (serialBuffer[5] == '0'? "disabled" : "enabled"));
					break;
				case 12:
					log_message(LOG_INFO, "Scanner Switch Read response: %s", serialBuffer + 3);
					break;
				default:
					log_message(LOG_NOTICE, "unknown S03* response: %s", serialBuffer);
			}
		}

		// *************** Process scale data *****************
		else if (strncmp(serialBuffer, "S11", 3) == 0) { // scale stable nonzero (request)
			mode = WEIGHING;
			if (strncmp(serialBuffer+3, lastWeight, 5) != 0) {
				log_message(LOG_NOTICE, "Weighed (per request): %s (previous was %s)", serialBuffer+3, lastWeight);
				write_file(scale_filepath, serialBuffer);
				strcpy(lastWeight, serialBuffer+3);
			}
		}
		else if (strncmp(serialBuffer, "S13", 3) == 0) { // scale status report
			// S13 Scale Status Read response: S13me12s where m = 0 english/1 metric, e = 0 enabled/1 disabled, s = 0 not ready or negative/1 weight transient/2 overweight/3 zero/4 stable+positive/5 sent
			log_message(LOG_NOTICE, "Scale status: %s, %s, %s",
					(serialBuffer[3] == '0'? "lb" : "kg"),
					(serialBuffer[4] == '0'? "enabled" : "disabled"),
					(serialBuffer[7] == '4'? "stable" : serialBuffer[7] == '3'? "zero" : "not ready/transient/over/underweight")
				);
			log_message(LOG_DEBUG + 1, ":");
		}
		else if (strncmp(serialBuffer, "S140", 4) == 0) { // scale not ready
			log_message(LOG_INFO, "?");
			mode = STARTUP;
		}
		else if (strncmp(serialBuffer, "S141", 4) == 0) { // scale unstable
			log_message(LOG_INFO, "~");
			mode = WEIGHING;
		}
		else if (strncmp(serialBuffer, "S142", 4) == 0) { // over capacity
			log_message(LOG_INFO, "+");
			mode = WEIGHING;
		}
		else if (strncmp(serialBuffer, "S143", 4) == 0) { // stable zero
			if (mode != READY) {
				log_message(LOG_NOTICE, "Scale now at stable zero weight.");
				mode = READY;
				write_file(scale_filepath, zeroWeight);
				strcpy(lastWeight, "0000");
				write_serial("S11"); // scale valid weight request: scale will send us "S11abcd" as soon as a nonzero weight stabilizes
			}
		}
		else if (strncmp(serialBuffer, "S144", 4) == 0) { // stable nonzero (monitor)
			mode = WEIGHING;
			if (strncmp(serialBuffer+4, lastWeight, 5) != 0) {
				log_message(LOG_INFO, "Weighed (per monitor): %s (previous was %s)", serialBuffer+4, lastWeight);
				write_serial("S334"); // monitor mode doesn't beep automatically; send good beep tone manually
				write_file(scale_filepath, serialBuffer);
				strcpy(lastWeight, serialBuffer+4);
			}
			else { // continuing stable non-zero weight
				log_message(LOG_INFO, "#");
			}
		}
		else if (strncmp(serialBuffer, "S145", 4) == 0) { // under zero
			log_message(LOG_INFO, "-");
			mode = WEIGHING;
		}

	}
	// while (mode != SHUTDOWN)

	log_message(LOG_DEBUG, "Exited main loop.");
	log_message(LOG_WARNING, "\n");
	exit(EXIT_SUCCESS);
}
