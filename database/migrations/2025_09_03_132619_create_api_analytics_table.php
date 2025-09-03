<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('api_analytics', function (Blueprint $table) {
            $table->id();

            // Request information
            $table->string('method'); // HTTP method (GET, POST, etc.)
            $table->string('endpoint'); // Full endpoint path
            $table->string('route_name')->nullable(); // Laravel route name
            $table->string('user_agent')->nullable(); // Request user agent
            $table->string('ip_address'); // Requester IP address
            $table->json('headers')->nullable(); // Request headers (filtered for privacy)
            $table->json('request_payload')->nullable(); // Request body/payload
            $table->json('query_parameters')->nullable(); // Query string parameters

            // Response information
            $table->integer('status_code'); // HTTP status code
            $table->json('response_data')->nullable(); // Response payload (truncated if too large)
            $table->integer('response_size_bytes')->nullable(); // Size of response in bytes

            // Timing information
            $table->timestamp('request_started_at'); // When request started
            $table->timestamp('request_completed_at'); // When request completed
            $table->integer('duration_ms'); // Request duration in milliseconds

            // Authentication/User context
            $table->unsignedBigInteger('user_id')->nullable(); // Authenticated user ID
            $table->string('api_key_hash')->nullable(); // Hash of API key if used
            $table->boolean('is_authenticated')->default(false); // Whether request was authenticated

            // Error tracking
            $table->boolean('has_error')->default(false); // Whether the request resulted in an error
            $table->string('error_type')->nullable(); // Type of error (validation, server, etc.)
            $table->text('error_message')->nullable(); // Error message (truncated)

            // Analytics metadata
            $table->string('endpoint_category')->nullable(); // Category: 'mcp', 'openapi', 'health', etc.
            $table->string('tool_name')->nullable(); // MCP tool name if applicable
            $table->string('referrer')->nullable(); // HTTP referrer header

            // Performance metrics
            $table->integer('memory_peak_usage_kb')->nullable(); // Peak memory usage in KB
            $table->integer('database_queries_count')->nullable(); // Number of DB queries executed

            $table->timestamps();

            // Indexes for efficient querying
            $table->index(['endpoint_category', 'created_at']);
            $table->index(['user_id', 'created_at']);
            $table->index(['method', 'endpoint']);
            $table->index(['status_code', 'created_at']);
            $table->index(['has_error', 'created_at']);
            $table->index(['tool_name', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('api_analytics');
    }
};
