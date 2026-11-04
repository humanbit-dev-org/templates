<?php

use App\Logging\CloudWatchHandler;
use App\Logging\Ec2InstanceId;
use App\Logging\EncryptedRotatingFileHandler;
use App\Logging\SetLogTimezone;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\NullHandler;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\SyslogUdpHandler;
use Monolog\Processor\PsrLogMessageProcessor;

$config = [
	/*
    |--------------------------------------------------------------------------
    | Default Log Channel
    |--------------------------------------------------------------------------
    |
    | This option defines the default log channel that is utilized to write
    | messages to your logs. The value provided here should match one of
    | the channels present in the list of "channels" configured below.
    |
    */

	"default" => env("LOG_CHANNEL", "stack"),

	/*
    |--------------------------------------------------------------------------
    | Log Timezone
    |--------------------------------------------------------------------------
    |
    | Fuso usato per i timestamp dei log, applicato via il tap SetLogTimezone.
    | Distinto da APP_TIMEZONE (che resta UTC e governa Carbon e i timestamp a DB):
    | qui serve solo a rendere i log leggibili in ora locale.
    |
    */

	"timezone" => env("LOG_TIMEZONE", "Europe/Rome"),

	/*
    |--------------------------------------------------------------------------
    | Deprecations Log Channel
    |--------------------------------------------------------------------------
    |
    | This option controls the log channel that should be used to log warnings
    | regarding deprecated PHP and library features. This allows you to get
    | your application ready for upcoming major versions of dependencies.
    |
    */

	"deprecations" => [
		"channel" => env("LOG_DEPRECATIONS_CHANNEL", "null"),
		"trace" => env("LOG_DEPRECATIONS_TRACE", false),
	],

	/*
    |--------------------------------------------------------------------------
    | Log Channels
    |--------------------------------------------------------------------------
    |
    | Here you may configure the log channels for your application. Laravel
    | utilizes the Monolog PHP logging library, which includes a variety
    | of powerful log handlers and formatters that you're free to use.
    |
    | Available drivers: "single", "daily", "slack", "syslog",
    |                    "errorlog", "monolog", "custom", "stack"
    |
    */

	"channels" => [
		"stack" => [
			"driver" => "stack",
			"channels" => explode(",", env("LOG_STACK", "single")),
			"ignore_exceptions" => false,
		],

		"single" => [
			"driver" => "single",
			"path" => storage_path("logs/laravel.log"),
			"level" => env("LOG_LEVEL", "debug"),
			"replace_placeholders" => true,
		],

		"daily" => [
			"driver" => "daily",
			"path" => storage_path("logs/laravel.log"),
			"level" => env("LOG_LEVEL", "debug"),
			"days" => env("LOG_DAILY_DAYS", 14),
			"replace_placeholders" => true,
		],

		"encrypted_daily" => [
			"driver" => "monolog",
			"handler" => EncryptedRotatingFileHandler::class,
			"handler_with" => [
				"path" => storage_path("logs/laravel.log"),
				"days" => (int) env("LOG_DAILY_DAYS", 30),
				"level" => env("LOG_LEVEL", "info"),
			],
			"formatter" => LineFormatter::class,
			"formatter_with" => [
				// %context%/%extra% are REQUIRED: without them LineFormatter silently
				// drops every context array passed to Log::info(...) & co.
				// ignoreEmptyContextAndExtra keeps lines clean when there is none.
				"format" => "[%datetime%] %channel%.%level_name%: %message% %context% %extra%\n",
				"dateFormat" => "Y-m-d H:i:s",
				"ignoreEmptyContextAndExtra" => true,
			],
			"replace_placeholders" => true,
		],

		"slack" => [
			"driver" => "slack",
			"url" => env("LOG_SLACK_WEBHOOK_URL"),
			"username" => env("LOG_SLACK_USERNAME", "Laravel Log"),
			"emoji" => env("LOG_SLACK_EMOJI", ":boom:"),
			"level" => env("LOG_LEVEL", "critical"),
			"replace_placeholders" => true,
		],

		"papertrail" => [
			"driver" => "monolog",
			"level" => env("LOG_LEVEL", "debug"),
			"handler" => env("LOG_PAPERTRAIL_HANDLER", SyslogUdpHandler::class),
			"handler_with" => [
				"host" => env("PAPERTRAIL_URL"),
				"port" => env("PAPERTRAIL_PORT"),
				"connectionString" => "tls://" . env("PAPERTRAIL_URL") . ":" . env("PAPERTRAIL_PORT"),
			],
			"processors" => [PsrLogMessageProcessor::class],
		],

		"stderr" => [
			"driver" => "monolog",
			"level" => env("LOG_LEVEL", "debug"),
			"handler" => StreamHandler::class,
			"formatter" => env("LOG_STDERR_FORMATTER"),
			"with" => [
				"stream" => "php://stderr",
			],
			"processors" => [PsrLogMessageProcessor::class],
		],

		"syslog" => [
			"driver" => "syslog",
			"level" => env("LOG_LEVEL", "debug"),
			"facility" => env("LOG_SYSLOG_FACILITY", LOG_USER),
			"replace_placeholders" => true,
		],

		"errorlog" => [
			"driver" => "errorlog",
			"level" => env("LOG_LEVEL", "debug"),
			"replace_placeholders" => true,
		],

		"null" => [
			"driver" => "monolog",
			"handler" => NullHandler::class,
		],

		"emergency" => [
			"driver" => "monolog",
			"handler" =>
				env("LOG_CHANNEL") === "encrypted_daily" ? EncryptedRotatingFileHandler::class : RotatingFileHandler::class,
			"handler_with" => [
				"path" => storage_path("logs/laravel.log"),
				"days" => (int) env("LOG_DAILY_DAYS", 30),
				"level" => "debug",
			],
			"formatter" => LineFormatter::class,
			"formatter_with" => [
				// %context%/%extra% are REQUIRED: without them LineFormatter silently
				// drops every context array passed to Log::info(...) & co.
				// ignoreEmptyContextAndExtra keeps lines clean when there is none.
				"format" => "[%datetime%] %channel%.%level_name%: %message% %context% %extra%\n",
				"dateFormat" => "Y-m-d H:i:s",
				"ignoreEmptyContextAndExtra" => true,
			],
			"replace_placeholders" => true,
		],
		"import" => [
			"driver" => "monolog",
			"handler" =>
				env("LOG_CHANNEL") === "encrypted_daily" ? EncryptedRotatingFileHandler::class : RotatingFileHandler::class,
			"handler_with" => [
				"path" => storage_path("logs/import.log"),
				"days" => (int) env("LOG_DAILY_DAYS", 30),
				"level" => env("LOG_LEVEL", "debug"),
			],
			"formatter" => LineFormatter::class,
			"formatter_with" => [
				// %context%/%extra% are REQUIRED: without them LineFormatter silently
				// drops every context array passed to Log::info(...) & co.
				// ignoreEmptyContextAndExtra keeps lines clean when there is none.
				"format" => "[%datetime%] %channel%.%level_name%: %message% %context% %extra%\n",
				"dateFormat" => "Y-m-d H:i:s",
				"ignoreEmptyContextAndExtra" => true,
			],
			"replace_placeholders" => true,
		],
		"tiraggio" => [
			"driver" => "monolog",
			"handler" =>
				env("LOG_CHANNEL") === "encrypted_daily" ? EncryptedRotatingFileHandler::class : RotatingFileHandler::class,
			"handler_with" => [
				"path" => database_path("import/tiraggio/import.log"),
				"days" => (int) env("LOG_DAILY_DAYS", 30),
				"level" => env("LOG_LEVEL", "debug"),
			],
			"formatter" => LineFormatter::class,
			"formatter_with" => [
				// %context%/%extra% are REQUIRED: without them LineFormatter silently
				// drops every context array passed to Log::info(...) & co.
				// ignoreEmptyContextAndExtra keeps lines clean when there is none.
				"format" => "[%datetime%] %channel%.%level_name%: %message% %context% %extra%\n",
				"dateFormat" => "Y-m-d H:i:s",
				"ignoreEmptyContextAndExtra" => true,
			],
			"replace_placeholders" => true,
		],
		"trattenuta" => [
			"driver" => "monolog",
			"handler" =>
				env("LOG_CHANNEL") === "encrypted_daily" ? EncryptedRotatingFileHandler::class : RotatingFileHandler::class,
			"handler_with" => [
				"path" => database_path("import/trattenuta/import.log"),
				"days" => (int) env("LOG_DAILY_DAYS", 30),
				"level" => env("LOG_LEVEL", "debug"),
			],
			"formatter" => LineFormatter::class,
			"formatter_with" => [
				// %context%/%extra% are REQUIRED: without them LineFormatter silently
				// drops every context array passed to Log::info(...) & co.
				// ignoreEmptyContextAndExtra keeps lines clean when there is none.
				"format" => "[%datetime%] %channel%.%level_name%: %message% %context% %extra%\n",
				"dateFormat" => "Y-m-d H:i:s",
				"ignoreEmptyContextAndExtra" => true,
			],
			"replace_placeholders" => true,
		],
		"mensile" => [
			"driver" => "monolog",
			"handler" =>
				env("LOG_CHANNEL") === "encrypted_daily" ? EncryptedRotatingFileHandler::class : RotatingFileHandler::class,
			"handler_with" => [
				"path" => database_path("import/mensile/import.log"),
				"days" => (int) env("LOG_DAILY_DAYS", 30),
				"level" => env("LOG_LEVEL", "debug"),
			],
			"formatter" => LineFormatter::class,
			"formatter_with" => [
				// %context%/%extra% are REQUIRED: without them LineFormatter silently
				// drops every context array passed to Log::info(...) & co.
				// ignoreEmptyContextAndExtra keeps lines clean when there is none.
				"format" => "[%datetime%] %channel%.%level_name%: %message% %context% %extra%\n",
				"dateFormat" => "Y-m-d H:i:s",
				"ignoreEmptyContextAndExtra" => true,
			],
			"replace_placeholders" => true,
		],
		"filiale" => [
			"driver" => "monolog",
			"handler" =>
				env("LOG_CHANNEL") === "encrypted_daily" ? EncryptedRotatingFileHandler::class : RotatingFileHandler::class,
			"handler_with" => [
				"path" => database_path("import/filiale/import.log"),
				"days" => (int) env("LOG_DAILY_DAYS", 30),
				"level" => env("LOG_LEVEL", "debug"),
			],
			"formatter" => LineFormatter::class,
			"formatter_with" => [
				// %context%/%extra% are REQUIRED: without them LineFormatter silently
				// drops every context array passed to Log::info(...) & co.
				// ignoreEmptyContextAndExtra keeps lines clean when there is none.
				"format" => "[%datetime%] %channel%.%level_name%: %message% %context% %extra%\n",
				"dateFormat" => "Y-m-d H:i:s",
				"ignoreEmptyContextAndExtra" => true,
			],
			"replace_placeholders" => true,
		],
		"delibera" => [
			"driver" => "monolog",
			"handler" =>
				env("LOG_CHANNEL") === "encrypted_daily" ? EncryptedRotatingFileHandler::class : RotatingFileHandler::class,
			"handler_with" => [
				"path" => database_path("import/delibera/import.log"),
				"days" => (int) env("LOG_DAILY_DAYS", 30),
				"level" => env("LOG_LEVEL", "debug"),
			],
			"formatter" => LineFormatter::class,
			"formatter_with" => [
				// %context%/%extra% are REQUIRED: without them LineFormatter silently
				// drops every context array passed to Log::info(...) & co.
				// ignoreEmptyContextAndExtra keeps lines clean when there is none.
				"format" => "[%datetime%] %channel%.%level_name%: %message% %context% %extra%\n",
				"dateFormat" => "Y-m-d H:i:s",
				"ignoreEmptyContextAndExtra" => true,
			],
			"replace_placeholders" => true,
		],
		"anagrafica" => [
			"driver" => "monolog",
			"handler" =>
				env("LOG_CHANNEL") === "encrypted_daily" ? EncryptedRotatingFileHandler::class : RotatingFileHandler::class,
			"handler_with" => [
				"path" => database_path("import/anagrafica/import.log"),
				"days" => (int) env("LOG_DAILY_DAYS", 30),
				"level" => env("LOG_LEVEL", "debug"),
			],
			"formatter" => LineFormatter::class,
			"formatter_with" => [
				// %context%/%extra% are REQUIRED: without them LineFormatter silently
				// drops every context array passed to Log::info(...) & co.
				// ignoreEmptyContextAndExtra keeps lines clean when there is none.
				"format" => "[%datetime%] %channel%.%level_name%: %message% %context% %extra%\n",
				"dateFormat" => "Y-m-d H:i:s",
				"ignoreEmptyContextAndExtra" => true,
			],
			"replace_placeholders" => true,
		],
		"proiezioni" => [
			"driver" => "monolog",
			"handler" =>
				env("LOG_CHANNEL") === "encrypted_daily" ? EncryptedRotatingFileHandler::class : RotatingFileHandler::class,
			"handler_with" => [
				"path" => storage_path("logs/proiezioni.log"),
				"days" => (int) env("LOG_DAILY_DAYS", 30),
				"level" => env("LOG_LEVEL", "debug"),
			],
			"formatter" => LineFormatter::class,
			"formatter_with" => [
				// %context%/%extra% are REQUIRED: without them LineFormatter silently
				// drops every context array passed to Log::info(...) & co.
				// ignoreEmptyContextAndExtra keeps lines clean when there is none.
				"format" => "[%datetime%] %channel%.%level_name%: %message% %context% %extra%\n",
				"dateFormat" => "Y-m-d H:i:s",
				"ignoreEmptyContextAndExtra" => true,
			],
			"replace_placeholders" => true,
		],
		// Assistant (AI support agent) audit trail: invocations, failures,
		// refusals and tool usage. Message content is redacted or omitted —
		// see config/assistant.php ('audit.log_content', 'audit.redact').
		"assistant" => [
			"driver" => "monolog",
			"handler" =>
				env("LOG_CHANNEL") === "encrypted_daily" ? EncryptedRotatingFileHandler::class : RotatingFileHandler::class,
			"handler_with" => [
				"path" => storage_path("logs/assistant.log"),
				"days" => (int) env("LOG_DAILY_DAYS", 30),
				"level" => env("LOG_LEVEL", "debug"),
			],
			"formatter" => LineFormatter::class,
			"formatter_with" => [
				// %context%/%extra% are REQUIRED: without them LineFormatter silently
				// drops every context array passed to Log::info(...) & co.
				// ignoreEmptyContextAndExtra keeps lines clean when there is none.
				"format" => "[%datetime%] %channel%.%level_name%: %message% %context% %extra%\n",
				"dateFormat" => "Y-m-d H:i:s",
				"ignoreEmptyContextAndExtra" => true,
			],
			"replace_placeholders" => true,
		],
	],

	/*
    |--------------------------------------------------------------------------
    | CloudWatch Logs
    |--------------------------------------------------------------------------
    |
    | Set AWS_LOG_GROUP (production only) and every application channel
    | writes to that log group instead of local files, in plain text: the
    | instances behind the load balancer each kept their own files. One stream
    | per channel and instance, e.g. "import/i-0abc123": the instance id comes
    | from the EC2 metadata, the hostname off EC2. The Backpack log viewer then
    | redirects to the CloudWatch console.
    |
    */

	"cloudwatch" => [
		"group" => env("AWS_LOG_GROUP"),
		"region" => env("AWS_DEFAULT_REGION", "eu-west-1"),
		"instance" => null,
	],
];

if (filled($config["cloudwatch"]["group"])) {
	// Asked only here, with CloudWatch on: elsewhere no metadata call at all.
	$config["cloudwatch"]["instance"] = Ec2InstanceId::resolve() ?? (gethostname() ?: "unknown");

	$cloudwatch = static fn(string $stream): array => [
		"driver" => "monolog",
		"handler" => CloudWatchHandler::class,
		"handler_with" => [
			"group" => $config["cloudwatch"]["group"],
			"stream" => $stream . "/" . $config["cloudwatch"]["instance"],
			"level" => env("LOG_LEVEL", "debug"),
			"region" => $config["cloudwatch"]["region"],
			"fallbackPath" => storage_path("logs/cloudwatch-fallback.log"),
		],
		"formatter" => LineFormatter::class,
		"formatter_with" => [
			// No %datetime%: every CloudWatch event carries its own timestamp.
			"format" => "%level_name%: %message% %context% %extra%\n",
			"ignoreEmptyContextAndExtra" => true,
		],
		"replace_placeholders" => true,
	];

	$config["channels"]["cloudwatch"] = $cloudwatch("laravel");
	$config["default"] = "cloudwatch";

	foreach (
		["import", "tiraggio", "trattenuta", "mensile", "filiale", "delibera", "anagrafica", "proiezioni", "assistant"]
		as $channel
	) {
		$config["channels"][$channel] = $cloudwatch($channel);
	}
}

// Write every channel's timestamps in logging.timezone (Europe/Rome) instead of
// UTC. Attached in a loop, after the CloudWatch overrides, so that every channel
// (including the stack driver, which builds its own logger, and channels added
// later) gets it without repeating 'tap' in each definition.
foreach ($config["channels"] as $name => $channel) {
	$config["channels"][$name]["tap"] = array_values(array_unique([...$channel["tap"] ?? [], SetLogTimezone::class]));
}

return $config;
