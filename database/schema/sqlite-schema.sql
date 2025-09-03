CREATE TABLE IF NOT EXISTS "migrations"(
  "id" integer primary key autoincrement not null,
  "migration" varchar not null,
  "batch" integer not null
);
CREATE TABLE IF NOT EXISTS "users"(
  "id" integer primary key autoincrement not null,
  "name" varchar not null,
  "email" varchar not null,
  "email_verified_at" datetime,
  "password" varchar not null,
  "remember_token" varchar,
  "created_at" datetime,
  "updated_at" datetime,
  "sleeper_username" varchar,
  "sleeper_user_id" varchar
);
CREATE UNIQUE INDEX "users_email_unique" on "users"("email");
CREATE TABLE IF NOT EXISTS "password_reset_tokens"(
  "email" varchar not null,
  "token" varchar not null,
  "created_at" datetime,
  primary key("email")
);
CREATE TABLE IF NOT EXISTS "sessions"(
  "id" varchar not null,
  "user_id" integer,
  "ip_address" varchar,
  "user_agent" text,
  "payload" text not null,
  "last_activity" integer not null,
  primary key("id")
);
CREATE INDEX "sessions_user_id_index" on "sessions"("user_id");
CREATE INDEX "sessions_last_activity_index" on "sessions"("last_activity");
CREATE TABLE IF NOT EXISTS "cache"(
  "key" varchar not null,
  "value" text not null,
  "expiration" integer not null,
  primary key("key")
);
CREATE TABLE IF NOT EXISTS "cache_locks"(
  "key" varchar not null,
  "owner" varchar not null,
  "expiration" integer not null,
  primary key("key")
);
CREATE TABLE IF NOT EXISTS "jobs"(
  "id" integer primary key autoincrement not null,
  "queue" varchar not null,
  "payload" text not null,
  "attempts" integer not null,
  "reserved_at" integer,
  "available_at" integer not null,
  "created_at" integer not null
);
CREATE INDEX "jobs_queue_index" on "jobs"("queue");
CREATE TABLE IF NOT EXISTS "job_batches"(
  "id" varchar not null,
  "name" varchar not null,
  "total_jobs" integer not null,
  "pending_jobs" integer not null,
  "failed_jobs" integer not null,
  "failed_job_ids" text not null,
  "options" text,
  "cancelled_at" integer,
  "created_at" integer not null,
  "finished_at" integer,
  primary key("id")
);
CREATE TABLE IF NOT EXISTS "failed_jobs"(
  "id" integer primary key autoincrement not null,
  "uuid" varchar not null,
  "connection" text not null,
  "queue" text not null,
  "payload" text not null,
  "exception" text not null,
  "failed_at" datetime not null default CURRENT_TIMESTAMP
);
CREATE UNIQUE INDEX "failed_jobs_uuid_unique" on "failed_jobs"("uuid");
CREATE TABLE IF NOT EXISTS "players"(
  "id" integer primary key autoincrement not null,
  "player_id" varchar not null,
  "sport" varchar not null default 'nfl',
  "first_name" varchar,
  "last_name" varchar,
  "full_name" varchar,
  "search_first_name" varchar,
  "search_last_name" varchar,
  "search_full_name" varchar,
  "search_rank" integer,
  "adp" float,
  "team" varchar,
  "position" varchar,
  "fantasy_positions" text,
  "status" varchar,
  "active" tinyint(1),
  "number" integer,
  "age" integer,
  "years_exp" integer,
  "college" varchar,
  "birth_date" date,
  "birth_city" varchar,
  "birth_state" varchar,
  "birth_country" varchar,
  "height" varchar,
  "weight" integer,
  "depth_chart_position" varchar,
  "depth_chart_order" integer,
  "injury_status" varchar,
  "injury_body_part" varchar,
  "injury_start_date" date,
  "injury_notes" text,
  "news_updated" integer,
  "hashtag" varchar,
  "espn_id" varchar,
  "yahoo_id" varchar,
  "rotowire_id" varchar,
  "pff_id" varchar,
  "sportradar_id" varchar,
  "fantasy_data_id" varchar,
  "gsis_id" varchar,
  "raw" text,
  "created_at" datetime,
  "updated_at" datetime,
  "adds_24h" integer,
  "drops_24h" integer,
  "adp_formatted" varchar,
  "times_drafted" integer,
  "adp_high" float,
  "adp_low" float,
  "adp_stdev" float,
  "bye_week" integer
);
CREATE UNIQUE INDEX "players_player_id_unique" on "players"("player_id");
CREATE INDEX "players_full_name_index" on "players"("full_name");
CREATE INDEX "players_team_index" on "players"("team");
CREATE INDEX "players_position_index" on "players"("position");
CREATE INDEX "players_status_index" on "players"("status");
CREATE INDEX "players_active_index" on "players"("active");
CREATE TABLE IF NOT EXISTS "api_analytics"(
  "id" integer primary key autoincrement not null,
  "method" varchar not null,
  "endpoint" varchar not null,
  "route_name" varchar,
  "user_agent" varchar,
  "ip_address" varchar not null,
  "headers" text,
  "request_payload" text,
  "query_parameters" text,
  "status_code" integer not null,
  "response_data" text,
  "response_size_bytes" integer,
  "request_started_at" datetime not null,
  "request_completed_at" datetime not null,
  "duration_ms" integer not null,
  "user_id" integer,
  "api_key_hash" varchar,
  "is_authenticated" tinyint(1) not null default '0',
  "has_error" tinyint(1) not null default '0',
  "error_type" varchar,
  "error_message" text,
  "endpoint_category" varchar,
  "tool_name" varchar,
  "referrer" varchar,
  "memory_peak_usage_kb" integer,
  "database_queries_count" integer,
  "created_at" datetime,
  "updated_at" datetime
);
CREATE INDEX "api_analytics_endpoint_category_created_at_index" on "api_analytics"(
  "endpoint_category",
  "created_at"
);
CREATE INDEX "api_analytics_user_id_created_at_index" on "api_analytics"(
  "user_id",
  "created_at"
);
CREATE INDEX "api_analytics_method_endpoint_index" on "api_analytics"(
  "method",
  "endpoint"
);
CREATE INDEX "api_analytics_status_code_created_at_index" on "api_analytics"(
  "status_code",
  "created_at"
);
CREATE INDEX "api_analytics_has_error_created_at_index" on "api_analytics"(
  "has_error",
  "created_at"
);
CREATE INDEX "api_analytics_tool_name_created_at_index" on "api_analytics"(
  "tool_name",
  "created_at"
);
CREATE TABLE IF NOT EXISTS "player_stats"(
  "id" integer primary key autoincrement not null,
  "player_id" varchar not null,
  "sport" varchar not null default 'nfl',
  "season" varchar not null,
  "week" integer not null,
  "season_type" varchar not null default 'regular',
  "date" date,
  "team" varchar,
  "opponent" varchar,
  "game_id" varchar,
  "company" varchar,
  "raw" text,
  "updated_at_ms" integer,
  "last_modified_ms" integer,
  "created_at" datetime,
  "updated_at" datetime,
  "pts_half_ppr" numeric,
  "pts_ppr" numeric,
  "pts_std" numeric,
  "pos_rank_half_ppr" integer,
  "pos_rank_ppr" integer,
  "pos_rank_std" integer,
  "gp" integer,
  "gs" integer,
  "gms_active" integer,
  "off_snp" integer,
  "tm_off_snp" integer,
  "tm_def_snp" integer,
  "tm_st_snp" integer,
  "rec" numeric,
  "rec_tgt" numeric,
  "rec_yd" numeric,
  "rec_td" numeric,
  "rec_fd" numeric,
  "rec_air_yd" numeric,
  "rec_rz_tgt" numeric,
  "rec_lng" integer,
  "rush_att" numeric,
  "rush_yd" numeric,
  "rush_td" numeric,
  "fum" numeric,
  "fum_lost" numeric,
  foreign key("player_id") references "players"("player_id") on delete cascade
);
CREATE UNIQUE INDEX "uniq_player_week_source" on "player_stats"(
  "player_id",
  "season",
  "week",
  "season_type",
  "company",
  "sport"
);
CREATE INDEX "player_stats_player_id_index" on "player_stats"("player_id");
CREATE INDEX "player_stats_sport_index" on "player_stats"("sport");
CREATE INDEX "player_stats_season_index" on "player_stats"("season");
CREATE INDEX "player_stats_week_index" on "player_stats"("week");
CREATE INDEX "player_stats_season_type_index" on "player_stats"("season_type");
CREATE INDEX "player_stats_team_index" on "player_stats"("team");
CREATE INDEX "player_stats_opponent_index" on "player_stats"("opponent");
CREATE TABLE IF NOT EXISTS "player_projections"(
  "id" integer primary key autoincrement not null,
  "player_id" varchar not null,
  "sport" varchar not null default 'nfl',
  "season" varchar not null,
  "week" integer not null,
  "season_type" varchar not null default 'regular',
  "date" date,
  "team" varchar,
  "opponent" varchar,
  "game_id" varchar,
  "company" varchar,
  "raw" text,
  "updated_at_ms" integer,
  "last_modified_ms" integer,
  "created_at" datetime,
  "updated_at" datetime,
  "pts_half_ppr" numeric,
  "pts_ppr" numeric,
  "pts_std" numeric,
  "adp_dd_ppr" integer,
  "pos_adp_dd_ppr" integer,
  "rec" numeric,
  "rec_tgt" numeric,
  "rec_yd" numeric,
  "rec_td" numeric,
  "rec_fd" numeric,
  "rush_att" numeric,
  "rush_yd" numeric,
  "fum" numeric,
  "fum_lost" numeric,
  foreign key("player_id") references "players"("player_id") on delete cascade
);
CREATE UNIQUE INDEX "uniq_proj_player_week_source" on "player_projections"(
  "player_id",
  "season",
  "week",
  "season_type",
  "company",
  "sport"
);
CREATE INDEX "player_projections_player_id_index" on "player_projections"(
  "player_id"
);
CREATE INDEX "player_projections_sport_index" on "player_projections"("sport");
CREATE INDEX "player_projections_season_index" on "player_projections"(
  "season"
);
CREATE INDEX "player_projections_week_index" on "player_projections"("week");
CREATE INDEX "player_projections_season_type_index" on "player_projections"(
  "season_type"
);
CREATE INDEX "player_projections_team_index" on "player_projections"("team");
CREATE INDEX "player_projections_opponent_index" on "player_projections"(
  "opponent"
);

INSERT INTO migrations VALUES(1,'0001_01_01_000000_create_users_table',1);
INSERT INTO migrations VALUES(2,'0001_01_01_000001_create_cache_table',1);
INSERT INTO migrations VALUES(3,'0001_01_01_000002_create_jobs_table',1);
INSERT INTO migrations VALUES(4,'2025_08_30_005323_create_players_table',2);
INSERT INTO migrations VALUES(5,'2025_08_30_032349_add_trending_columns_to_players_table',3);
INSERT INTO migrations VALUES(6,'2025_08_30_042242_add_adp_columns_to_players_table',4);
INSERT INTO migrations VALUES(7,'2025_09_02_154130_add_sleeper_fields_to_users_table',5);
INSERT INTO migrations VALUES(25,'2025_09_03_132619_create_api_analytics_table',6);
INSERT INTO migrations VALUES(26,'2025_09_03_195823_create_player_stats_table',6);
INSERT INTO migrations VALUES(27,'2025_09_03_195836_create_player_projections_table',6);
INSERT INTO migrations VALUES(28,'2025_09_03_210000_drop_player_db_id_from_player_projections',6);
INSERT INTO migrations VALUES(29,'2025_09_03_210100_update_player_stats_flat_columns',6);
INSERT INTO migrations VALUES(30,'2025_09_03_210110_update_player_projections_flat_columns',6);
INSERT INTO migrations VALUES(31,'2025_09_03_224309_add_missing_columns_to_player_stats_table',7);
INSERT INTO migrations VALUES(32,'2025_09_03_224322_add_missing_columns_to_player_projections_table',7);
