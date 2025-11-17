<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Ramp Logger Configuration
    |--------------------------------------------------------------------------
    |
    | This file contains configuration options for the Ramp Logger package.
    | These settings control how logs are structured and sent to Datadog.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Logger Status
    |--------------------------------------------------------------------------
    |
    | Whether the Ramp Logger is enabled. When disabled, the package will
    | not intercept or modify logging behavior.
    |
    */
    'enabled' => env('RAMP_LOGGER_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Service Configuration
    |--------------------------------------------------------------------------
    |
    | These settings identify your service in Datadog. The service name
    | should match your ECS service name for proper correlation.
    |
    */
    'service_name' => env('RAMP_LOGGER_SERVICE_NAME', env('DD_SERVICE', env('APP_NAME', 'laravel'))),
    'environment' => env('RAMP_LOGGER_ENVIRONMENT', env('DD_ENV', env('APP_ENV', 'production'))),
    'version' => env('RAMP_LOGGER_VERSION', env('DD_VERSION', '1.0.0')),

    /*
    |--------------------------------------------------------------------------
    | Default Tags
    |--------------------------------------------------------------------------
    |
    | Tags that will be applied to all log entries. These are useful for
    | service-wide categorization and filtering in Datadog.
    |
    */
    'default_tags' => [
        'application' => env('APP_NAME', 'laravel'),
        'environment' => env('APP_ENV', 'production'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
        'stack' => 'ramp-technology',
    ],

    /*
    |--------------------------------------------------------------------------
    | Request Logging
    |--------------------------------------------------------------------------
    |
    | Configuration for HTTP request and response logging via middleware.
    |
    */
    'log_requests' => env('RAMP_LOGGER_LOG_REQUESTS', true),
    'log_request_body' => env('RAMP_LOGGER_LOG_REQUEST_BODY', false),
    'log_response_body' => env('RAMP_LOGGER_LOG_RESPONSE_BODY', false),

    /*
    |--------------------------------------------------------------------------
    | Performance Monitoring
    |--------------------------------------------------------------------------
    |
    | Settings for performance-related logging features.
    |
    */
    'log_memory_usage' => env('RAMP_LOGGER_LOG_MEMORY', true),
    'log_execution_time' => env('RAMP_LOGGER_LOG_EXECUTION_TIME', true),

    /*
    |--------------------------------------------------------------------------
    | Datadog Configuration
    |--------------------------------------------------------------------------
    |
    | Settings for Datadog integration. These should match your existing
    | Datadog configuration to ensure proper trace correlation.
    |
    */
    'datadog' => [
        'agent_host' => env('DD_AGENT_HOST', 'datadog-agent'),
        'trace_agent_port' => env('DD_TRACE_AGENT_PORT', 8126),
        'logs_injection' => env('DD_LOGS_INJECTION', true),
        'trace_enabled' => env('DD_TRACE_ENABLED', true),
        'site' => env('DD_SITE', 'datadoghq.com'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Security Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for data redaction and security measures.
    |
    */
    'redact_sensitive_data' => env('RAMP_LOGGER_REDACT_SENSITIVE', true),
    'sensitive_fields' => [
        'password',
        'token',
        'secret',
        'key',
        'authorization',
        'credit_card',
        'card_number',
        'cvv',
        'pin',
        'ssn',
        'api_key',
        'private_key',
    ],

    /*
    |--------------------------------------------------------------------------
    | Log Level Configuration
    |--------------------------------------------------------------------------
    |
    | Minimum log level that will be processed by the Ramp Logger.
    | Available levels: emergency, alert, critical, error, warning, notice, info, debug
    |
    */
    'level' => env('RAMP_LOGGER_LEVEL', 'debug'),

    /*
    |--------------------------------------------------------------------------
    | Service-Specific Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration options that can be used for service-specific behavior.
    | These can be used to add service-specific tags or context.
    |
    */
    'service_config' => [
        // Payment service specific
        'payman' => [
            'tags' => ['domain' => 'payments', 'type' => 'financial'],
        ],
        
        // Gateway service specific
        'gateman' => [
            'tags' => ['domain' => 'gateway', 'type' => 'api'],
        ],
        
        // Banking service specific
        'bankman' => [
            'tags' => ['domain' => 'banking', 'type' => 'financial'],
        ],
        
        // Core service specific
        'ramp-core' => [
            'tags' => ['domain' => 'core', 'type' => 'web'],
        ],

        // Vault service specific
        'vaultman' => [
            'tags' => ['domain' => 'vault', 'type' => 'security'],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | APM (Application Performance Monitoring) Configuration
    |--------------------------------------------------------------------------
    |
    | Settings for Datadog APM integration. This provides distributed tracing,
    | performance monitoring, and custom metrics collection.
    |
    */
    'apm' => [
        /*
        | Enable/disable APM functionality
        */
        'enabled' => env('DD_TRACE_ENABLED', env('RAMP_APM_ENABLED', true)),

        /*
        | Datadog Agent Configuration
        | Should point to the Datadog agent (usually localhost:8126 in ECS sidecar)
        */
        'agent_host' => env('DD_AGENT_HOST', 'localhost'),
        'agent_port' => env('DD_TRACE_AGENT_PORT', 8126),

        /*
        | Custom Metrics Configuration
        */
        'metrics_enabled' => env('DD_DOGSTATSD_ENABLED', env('RAMP_APM_METRICS_ENABLED', true)),
        'metrics_namespace' => env('RAMP_APM_METRICS_NAMESPACE', 'bankman'),

        /*
        | Database Monitoring
        */
        'database_monitoring' => env('RAMP_APM_DATABASE_MONITORING', true),
        'slow_query_threshold' => env('RAMP_APM_SLOW_QUERY_THRESHOLD', 1000), // milliseconds

        /*
        | Request Monitoring
        */
        'request_monitoring' => env('RAMP_APM_REQUEST_MONITORING', true),
        'error_tracking' => env('RAMP_APM_ERROR_TRACKING', true),
        
        /*
        | Trace Naming Configuration
        */
        'use_route_names' => env('RAMP_APM_USE_ROUTE_NAMES', true),
        'resource_naming' => env('RAMP_APM_RESOURCE_NAMING', true),
    ],
];
