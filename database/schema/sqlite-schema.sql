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
  "phone" varchar,
  "date_of_birth" date,
  "is_active" varchar not null default '1',
  "gender" varchar,
  "is_vip" varchar not null default '0',
  "vip_since" datetime,
  "avatar_url" varchar,
  "bio" text,
  "preferences" text,
  "remember_token" varchar,
  "created_at" datetime,
  "updated_at" datetime
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
CREATE TABLE IF NOT EXISTS "roles"(
  "id" integer primary key autoincrement not null,
  "name" varchar not null,
  "display_name" varchar not null,
  "description" text,
  "is_admin" varchar not null default '0',
  "created_at" datetime,
  "updated_at" datetime
);
CREATE TABLE IF NOT EXISTS "permissions"(
  "id" integer primary key autoincrement not null,
  "name" varchar not null,
  "display_name" varchar not null,
  "description" text,
  "category" varchar not null default 'general',
  "created_at" datetime,
  "updated_at" datetime
);
CREATE TABLE IF NOT EXISTS "role_user"(
  "id" integer primary key autoincrement not null,
  "role_id" integer not null,
  "user_id" integer not null,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("user_id") references "users"("id") on delete cascade,
  foreign key("role_id") references "roles"("id") on delete cascade
);
CREATE TABLE IF NOT EXISTS "permission_role"(
  "id" integer primary key autoincrement not null,
  "permission_id" integer not null,
  "role_id" integer not null,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("role_id") references "roles"("id") on delete cascade,
  foreign key("permission_id") references "permissions"("id") on delete cascade
);
CREATE TABLE IF NOT EXISTS "categories"(
  "id" integer primary key autoincrement not null,
  "name" varchar not null,
  "slug" varchar not null,
  "description" text,
  "is_active" varchar not null default '1',
  "type" varchar not null default 'category',
  "parent_id" integer,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("parent_id") references "categories"("id") on delete cascade
);
CREATE UNIQUE INDEX "categories_slug_unique" on "categories"("slug");
CREATE INDEX "categories_parent_id_index" on "categories"("parent_id");
CREATE INDEX "categories_type_is_active_index" on "categories"(
  "type",
  "is_active"
);
CREATE TABLE IF NOT EXISTS "brands"(
  "id" integer primary key autoincrement not null,
  "name" varchar not null,
  "slug" varchar not null,
  "description" text,
  "is_active" varchar not null default '1',
  "created_at" datetime,
  "updated_at" datetime
);
CREATE UNIQUE INDEX "brands_slug_unique" on "brands"("slug");
CREATE INDEX "brands_is_active_index" on "brands"("is_active");
CREATE TABLE IF NOT EXISTS "products"(
  "id" integer primary key autoincrement not null,
  "name" varchar not null,
  "description" text not null,
  "price" numeric not null,
  "category" varchar,
  "in_stock" varchar not null default '0',
  "features" text,
  "hover_image" varchar,
  "category_id" integer,
  "brand_id" integer,
  "slug" varchar not null,
  "tags" text,
  "is_active" varchar not null default '1',
  "is_featured" varchar not null default '0',
  "meta_title" varchar,
  "meta_description" text,
  "product_images" text,
  "product_code" varchar not null,
  "promotional_price" numeric,
  "promotional_price_set_at" datetime,
  "applied_promotion_id" integer,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("brand_id") references "brands"("id") on delete set null,
  foreign key("category_id") references "categories"("id") on delete set null
);
CREATE UNIQUE INDEX "products_product_code_unique" on "products"(
  "product_code"
);
CREATE UNIQUE INDEX "products_slug_unique" on "products"("slug");
CREATE INDEX "products_is_active_is_featured_index" on "products"(
  "is_active",
  "is_featured"
);
CREATE TABLE IF NOT EXISTS "product_variants"(
  "id" integer primary key autoincrement not null,
  "product_id" integer not null,
  "sku" varchar not null,
  "color" varchar not null,
  "color_code" varchar,
  "price" numeric,
  "compare_at_price" numeric,
  "is_active" varchar not null default '1',
  "created_at" datetime,
  "updated_at" datetime,
  "sizes" text,
  foreign key("product_id") references "products"("id") on delete cascade
);
CREATE UNIQUE INDEX "unique_product_color" on "product_variants"(
  "product_id",
  "color"
);
CREATE UNIQUE INDEX "product_variants_sku_unique" on "product_variants"("sku");
CREATE INDEX "product_variants_sku_index" on "product_variants"("sku");
CREATE INDEX "product_variants_product_id_color_index" on "product_variants"(
  "product_id",
  "color"
);
CREATE INDEX "product_variants_product_id_is_active_index" on "product_variants"(
  "product_id",
  "is_active"
);
CREATE TABLE IF NOT EXISTS "addresses"(
  "id" integer primary key autoincrement not null,
  "user_id" integer not null,
  "type" varchar not null default 'shipping',
  "name" varchar not null,
  "address_line_1" varchar not null,
  "address_line_2" varchar,
  "postal_code" varchar,
  "country" varchar not null,
  "is_default" varchar not null default '0',
  "created_at" datetime,
  "updated_at" datetime,
  "phone" varchar,
  "township" varchar,
  "state_region" varchar,
  "company" varchar,
  foreign key("user_id") references "users"("id") on delete cascade
);
CREATE INDEX "addresses_user_id_is_default_index" on "addresses"(
  "user_id",
  "is_default"
);
CREATE INDEX "addresses_user_id_type_index" on "addresses"("user_id", "type");
CREATE TABLE IF NOT EXISTS "township_delivery_prices"(
  "id" integer primary key autoincrement not null,
  "township_name" varchar not null,
  "township_name_mm" varchar,
  "state_region" varchar not null,
  "state_region_mm" varchar,
  "delivery_price" numeric not null,
  "estimated_days" varchar not null default '1',
  "is_active" varchar not null default '1',
  "notes" text,
  "created_at" datetime,
  "updated_at" datetime
);
CREATE INDEX "township_delivery_prices_is_active_index" on "township_delivery_prices"(
  "is_active"
);
CREATE INDEX "township_delivery_prices_township_name_state_region_index" on "township_delivery_prices"(
  "township_name",
  "state_region"
);
CREATE TABLE IF NOT EXISTS "orders"(
  "id" integer primary key autoincrement not null,
  "order_number" varchar not null,
  "user_id" integer not null,
  "status" varchar not null default 'pending',
  "subtotal" numeric not null,
  "tax_amount" numeric not null default '0',
  "shipping_amount" numeric not null default '0',
  "discount_amount" numeric not null default '0',
  "total_amount" numeric not null,
  "currency" varchar not null default 'USD',
  "billing_address" text,
  "shipping_address" text not null,
  "payment_status" varchar not null default 'pending',
  "payment_method" varchar,
  "payment_screenshot" varchar,
  "payment_notes" text,
  "notes" text,
  "shipped_at" datetime,
  "delivered_at" datetime,
  "created_at" datetime,
  "updated_at" datetime,
  "delivery_fee" numeric not null default '0',
  "township_delivery_id" integer,
  "shipping_address_id" integer,
  "approved_by" integer,
  "approved_at" datetime,
  "rejection_reason" text,
  foreign key("user_id") references "users"("id") on delete cascade,
  foreign key("township_delivery_id") references "township_delivery_prices"("id"),
  foreign key("shipping_address_id") references "addresses"("id"),
  foreign key("approved_by") references "users"("id")
);
CREATE INDEX "orders_user_id_status_index" on "orders"("user_id", "status");
CREATE INDEX "orders_status_index" on "orders"("status");
CREATE INDEX "orders_payment_status_index" on "orders"("payment_status");
CREATE UNIQUE INDEX "orders_order_number_unique" on "orders"("order_number");
CREATE TABLE IF NOT EXISTS "order_items"(
  "id" integer primary key autoincrement not null,
  "order_id" integer not null,
  "product_id" integer not null,
  "product_variant_id" integer,
  "product_name" varchar not null,
  "product_sku" varchar,
  "price" numeric not null,
  "quantity" varchar not null,
  "total" numeric not null,
  "product_snapshot" text,
  "created_at" datetime,
  "updated_at" datetime,
  "variant_color" varchar,
  "variant_size" varchar,
  foreign key("product_variant_id") references "product_variants"("id") on delete set null,
  foreign key("product_id") references "products"("id") on delete cascade,
  foreign key("order_id") references "orders"("id") on delete cascade
);
CREATE INDEX "order_items_product_id_index" on "order_items"("product_id");
CREATE INDEX "order_items_order_id_index" on "order_items"("order_id");
CREATE TABLE IF NOT EXISTS "promotions"(
  "id" integer primary key autoincrement not null,
  "name" varchar not null,
  "slug" varchar not null,
  "description" text,
  "type" varchar not null default 'percentage',
  "value" numeric not null,
  "minimum_amount" numeric,
  "maximum_discount" numeric,
  "usage_limit" varchar,
  "usage_limit_per_customer" varchar,
  "used_count" varchar not null default '0',
  "start_date" datetime not null,
  "end_date" datetime,
  "is_active" varchar not null default '1',
  "applies_to" varchar not null default 'all',
  "conditions" text,
  "created_by" integer not null,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("created_by") references "users"("id")
);
CREATE UNIQUE INDEX "promotions_slug_unique" on "promotions"("slug");
CREATE INDEX "promotions_slug_index" on "promotions"("slug");
CREATE INDEX "promotions_is_active_start_date_end_date_index" on "promotions"(
  "is_active",
  "start_date",
  "end_date"
);
CREATE TABLE IF NOT EXISTS "promotion_products"(
  "id" integer primary key autoincrement not null,
  "promotion_id" integer not null,
  "product_id" integer not null,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("product_id") references "products"("id") on delete cascade,
  foreign key("promotion_id") references "promotions"("id") on delete cascade
);
CREATE UNIQUE INDEX "promotion_products_promotion_id_product_id_unique" on "promotion_products"(
  "promotion_id",
  "product_id"
);
CREATE TABLE IF NOT EXISTS "promotion_categories"(
  "id" integer primary key autoincrement not null,
  "promotion_id" integer not null,
  "category_id" integer not null,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("category_id") references "categories"("id") on delete cascade,
  foreign key("promotion_id") references "promotions"("id") on delete cascade
);
CREATE UNIQUE INDEX "promotion_categories_promotion_id_category_id_unique" on "promotion_categories"(
  "promotion_id",
  "category_id"
);
CREATE TABLE IF NOT EXISTS "promotion_brands"(
  "id" integer primary key autoincrement not null,
  "promotion_id" integer not null,
  "brand_id" integer not null,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("brand_id") references "brands"("id") on delete cascade,
  foreign key("promotion_id") references "promotions"("id") on delete cascade
);
CREATE UNIQUE INDEX "promotion_brands_promotion_id_brand_id_unique" on "promotion_brands"(
  "promotion_id",
  "brand_id"
);
CREATE TABLE IF NOT EXISTS "wholesale_applications"(
  "id" integer primary key autoincrement not null,
  "business_name" varchar not null,
  "business_type" varchar not null default 'retailer',
  "full_name" varchar not null,
  "email" varchar not null,
  "phone_number" varchar not null,
  "country_region" varchar not null,
  "city" varchar not null,
  "state_province" varchar,
  "product_interests" text not null,
  "additional_information" text,
  "shipping_address" text not null,
  "status" varchar not null default 'pending',
  "admin_notes" text,
  "reviewed_at" datetime,
  "reviewed_by" integer,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("reviewed_by") references "users"("id") on delete set null
);
CREATE INDEX "wholesale_applications_email_index" on "wholesale_applications"(
  "email"
);
CREATE INDEX "wholesale_applications_status_created_at_index" on "wholesale_applications"(
  "status",
  "created_at"
);
CREATE TABLE IF NOT EXISTS "contact_messages"(
  "id" integer primary key autoincrement not null,
  "name" varchar not null,
  "email" varchar not null,
  "phone" varchar,
  "subject" varchar not null,
  "message" text not null,
  "status" varchar check("status" in('new', 'read', 'replied', 'resolved', 'archived')) not null default 'new',
  "admin_notes" text,
  "admin_reply_subject" varchar,
  "admin_reply_message" text,
  "replied_at" datetime,
  "replied_by" integer,
  "ip_address" varchar,
  "user_agent" varchar,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("replied_by") references "users"("id") on delete set null
);
CREATE INDEX "contact_messages_status_index" on "contact_messages"("status");
CREATE INDEX "contact_messages_created_at_index" on "contact_messages"(
  "created_at"
);
CREATE INDEX "contact_messages_email_index" on "contact_messages"("email");
CREATE TABLE IF NOT EXISTS "exchange_requests"(
  "id" integer primary key autoincrement not null,
  "order_number" varchar not null,
  "date_of_purchase" date not null,
  "email_address" varchar not null,
  "phone_number" varchar not null,
  "product_to_exchange_name" varchar not null,
  "size" varchar not null,
  "item_color" varchar not null,
  "reason_for_exchange" varchar not null,
  "replacement_request_item" varchar not null,
  "replacement_request_name" varchar not null,
  "additional_comments" text,
  "status" varchar not null default 'pending',
  "admin_notes" text,
  "processed_by" integer,
  "processed_at" datetime,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("processed_by") references "users"("id") on delete set null
);
CREATE INDEX "exchange_requests_email_address_index" on "exchange_requests"(
  "email_address"
);
CREATE INDEX "exchange_requests_order_number_index" on "exchange_requests"(
  "order_number"
);
CREATE INDEX "exchange_requests_status_created_at_index" on "exchange_requests"(
  "status",
  "created_at"
);
CREATE TABLE IF NOT EXISTS "coupons"(
  "id" integer primary key autoincrement not null,
  "code" varchar not null,
  "name" varchar not null,
  "discount_type" varchar not null,
  "discount_value" numeric not null,
  "minimum_amount" numeric,
  "maximum_discount" numeric,
  "usage_limit" varchar,
  "used_count" varchar not null default '0',
  "is_active" varchar not null default '1',
  "starts_at" datetime,
  "expires_at" datetime,
  "description" text,
  "created_at" datetime,
  "updated_at" datetime
);
CREATE UNIQUE INDEX "coupons_code_unique" on "coupons"("code");
CREATE TABLE IF NOT EXISTS "personal_access_tokens"(
  "id" integer primary key autoincrement not null,
  "tokenable_type" varchar not null,
  "tokenable_id" integer not null,
  "name" varchar not null,
  "token" varchar not null,
  "abilities" text,
  "last_used_at" datetime,
  "expires_at" datetime,
  "created_at" datetime,
  "updated_at" datetime
);
CREATE UNIQUE INDEX "personal_access_tokens_token_unique" on "personal_access_tokens"(
  "token"
);
CREATE INDEX "personal_access_tokens_expires_at_index" on "personal_access_tokens"(
  "expires_at"
);
CREATE INDEX "personal_access_tokens_tokenable_type_tokenable_id_index" on "personal_access_tokens"(
  "tokenable_type",
  "tokenable_id"
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
CREATE TABLE IF NOT EXISTS "jobs"(
  "id" integer primary key autoincrement not null,
  "queue" varchar not null,
  "payload" text not null,
  "attempts" varchar not null,
  "reserved_at" varchar,
  "available_at" varchar not null,
  "created_at" varchar not null
);
CREATE INDEX "jobs_queue_index" on "jobs"("queue");
CREATE TABLE IF NOT EXISTS "job_batches"(
  "id" integer primary key autoincrement not null,
  "name" varchar not null,
  "total_jobs" varchar not null,
  "pending_jobs" varchar not null,
  "failed_jobs" varchar not null,
  "failed_job_ids" text not null,
  "options" text,
  "cancelled_at" varchar,
  "created_at" varchar not null,
  "finished_at" varchar
);
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

INSERT INTO migrations VALUES(29,'2024_01_01_000001_create_users_table',1);
INSERT INTO migrations VALUES(30,'2024_01_01_000002_create_password_reset_tokens_table',1);
INSERT INTO migrations VALUES(31,'2024_01_01_000003_create_sessions_table',1);
INSERT INTO migrations VALUES(32,'2024_01_01_000004_create_roles_table',1);
INSERT INTO migrations VALUES(33,'2024_01_01_000005_create_permissions_table',1);
INSERT INTO migrations VALUES(34,'2024_01_01_000006_create_role_user_table',1);
INSERT INTO migrations VALUES(35,'2024_01_01_000007_create_permission_role_table',1);
INSERT INTO migrations VALUES(36,'2024_01_01_000008_create_categories_table',1);
INSERT INTO migrations VALUES(37,'2024_01_01_000009_create_brands_table',1);
INSERT INTO migrations VALUES(38,'2024_01_01_000011_create_products_table',1);
INSERT INTO migrations VALUES(39,'2024_01_01_000012_create_product_variants_table',1);
INSERT INTO migrations VALUES(40,'2024_01_01_000013_create_addresses_table',1);
INSERT INTO migrations VALUES(41,'2024_01_01_000014_create_township_delivery_prices_table',1);
INSERT INTO migrations VALUES(42,'2024_01_01_000015_create_orders_table',1);
INSERT INTO migrations VALUES(43,'2024_01_01_000016_create_order_items_table',1);
INSERT INTO migrations VALUES(44,'2024_01_01_000018_create_promotions_table',1);
INSERT INTO migrations VALUES(45,'2024_01_01_000019_create_promotion_products_table',1);
INSERT INTO migrations VALUES(46,'2024_01_01_000020_create_promotion_categories_table',1);
INSERT INTO migrations VALUES(47,'2024_01_01_000021_create_promotion_brands_table',1);
INSERT INTO migrations VALUES(48,'2024_01_01_000022_create_wholesale_applications_table',1);
INSERT INTO migrations VALUES(49,'2024_01_01_000023_create_contact_messages_table',1);
INSERT INTO migrations VALUES(50,'2024_01_01_000024_create_exchange_requests_table',1);
INSERT INTO migrations VALUES(51,'2024_01_01_000025_create_coupons_table',1);
INSERT INTO migrations VALUES(52,'2024_01_01_000026_create_personal_access_tokens_table',1);
INSERT INTO migrations VALUES(53,'2024_01_01_000027_create_failed_jobs_table',1);
INSERT INTO migrations VALUES(54,'2024_01_01_000028_create_jobs_table',1);
INSERT INTO migrations VALUES(55,'2024_01_01_000029_create_job_batches_table',1);
INSERT INTO migrations VALUES(56,'2024_01_01_000030_create_cache_table',1);
