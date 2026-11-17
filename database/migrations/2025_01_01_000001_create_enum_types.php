<?php

use Illuminate\Database\Migrations\Migration;
use App\Support\SchemaCompat;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up()
    {
        // Create all PostgreSQL ENUM types (with IF NOT EXISTS check)
        // User and Address enums
        SchemaCompat::statement("DO $$ BEGIN CREATE TYPE user_type_enum AS ENUM ('customer', 'partner', 'admin'); EXCEPTION WHEN duplicate_object THEN null; END $$;");
        SchemaCompat::statement("DO $$ BEGIN CREATE TYPE address_type_enum AS ENUM ('home', 'work', 'other'); EXCEPTION WHEN duplicate_object THEN null; END $$;");

        // Service and Partner enums
        SchemaCompat::statement("DO $$ BEGIN CREATE TYPE unit_enum AS ENUM ('hour', 'sqft', 'room', 'piece', 'kg', 'liter', 'fixed'); EXCEPTION WHEN duplicate_object THEN null; END $$;");
        SchemaCompat::statement("DO $$ BEGIN CREATE TYPE partner_status_enum AS ENUM ('onboarding', 'active', 'inactive', 'suspended', 'pending_verification'); EXCEPTION WHEN duplicate_object THEN null; END $$;");

        // Order and Service Request enums - consolidated from multiple migrations
        SchemaCompat::statement("DO $$ BEGIN CREATE TYPE order_status_enum AS ENUM (
            'created', 'pending', 'pending_payment', 'awaiting_partner', 'partner_assigned', 'assigned', 'accepted', 'rejected',
            'confirmed', 'enroute', 'in_progress', 'working', 'completed', 'closed',
            'cancelled_by_customer', 'cancelled_by_partner', 'cancelled_by_admin', 'cancelled', 'refunded'
        ); EXCEPTION WHEN duplicate_object THEN null; END $$;");

        SchemaCompat::statement("DO $$ BEGIN CREATE TYPE service_request_status_enum AS ENUM (
            'pending', 'assigned', 'accepted', 'rejected', 'in_progress', 'working', 'completed', 'cancelled'
        ); EXCEPTION WHEN duplicate_object THEN null; END $$;");

        // Payment and Transaction enums
        SchemaCompat::statement("DO $$ BEGIN CREATE TYPE transaction_type_enum AS ENUM ('payment', 'refund', 'payout', 'fee', 'commission'); EXCEPTION WHEN duplicate_object THEN null; END $$;");
        SchemaCompat::statement("DO $$ BEGIN CREATE TYPE transaction_status_enum AS ENUM ('pending', 'completed', 'failed', 'cancelled', 'refunded'); EXCEPTION WHEN duplicate_object THEN null; END $$;");
        SchemaCompat::statement("DO $$ BEGIN CREATE TYPE account_type_enum AS ENUM ('asset', 'liability', 'equity', 'revenue', 'expense'); EXCEPTION WHEN duplicate_object THEN null; END $$;");
        SchemaCompat::statement("DO $$ BEGIN CREATE TYPE payout_status_enum AS ENUM ('pending', 'processing', 'completed', 'failed', 'cancelled'); EXCEPTION WHEN duplicate_object THEN null; END $$;");

        // Promotions and Support enums
        SchemaCompat::statement("DO $$ BEGIN CREATE TYPE promo_type_enum AS ENUM ('percentage', 'fixed_amount', 'free_service'); EXCEPTION WHEN duplicate_object THEN null; END $$;");
        SchemaCompat::statement("DO $$ BEGIN CREATE TYPE support_ticket_status_enum AS ENUM ('open', 'in_progress', 'waiting_customer', 'resolved', 'closed'); EXCEPTION WHEN duplicate_object THEN null; END $$;");
        SchemaCompat::statement("DO $$ BEGIN CREATE TYPE notification_type_enum AS ENUM ('info', 'success', 'warning', 'error', 'order_update', 'payment', 'promotion'); EXCEPTION WHEN duplicate_object THEN null; END $$;");
    }

    public function down()
    {
        SchemaCompat::statement("DROP TYPE IF EXISTS notification_type_enum");
        SchemaCompat::statement("DROP TYPE IF EXISTS support_ticket_status_enum");
        SchemaCompat::statement("DROP TYPE IF EXISTS promo_type_enum");
        SchemaCompat::statement("DROP TYPE IF EXISTS payout_status_enum");
        SchemaCompat::statement("DROP TYPE IF EXISTS account_type_enum");
        SchemaCompat::statement("DROP TYPE IF EXISTS transaction_status_enum");
        SchemaCompat::statement("DROP TYPE IF EXISTS transaction_type_enum");
        SchemaCompat::statement("DROP TYPE IF EXISTS service_request_status_enum");
        SchemaCompat::statement("DROP TYPE IF EXISTS order_status_enum");
        SchemaCompat::statement("DROP TYPE IF EXISTS partner_status_enum");
        SchemaCompat::statement("DROP TYPE IF EXISTS unit_enum");
        SchemaCompat::statement("DROP TYPE IF EXISTS address_type_enum");
        SchemaCompat::statement("DROP TYPE IF EXISTS user_type_enum");
    }
};
