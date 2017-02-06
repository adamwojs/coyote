<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateUsersMicroblogColumn extends Migration
{
    use SchemaBuilder;

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $this->schema->table('users', function (Blueprint $table) {
            $table->integer('microblog')->default(0);
        });

        DB::unprepared('
            CREATE OR REPLACE FUNCTION public.after_microblog_insert() RETURNS trigger LANGUAGE plpgsql AS $$ 
            BEGIN
                IF (NEW.parent_id IS NULL AND NEW.user_id IS NOT NULL) THEN
                    UPDATE users SET microblog = microblog + 1 WHERE "id" = NEW.user_id;	
                END IF;
            
                RETURN NEW;
            END;$$
        ');

        DB::unprepared('
            CREATE TRIGGER after_microblog_insert
              AFTER INSERT
              ON public.microblogs
              FOR EACH ROW
              EXECUTE PROCEDURE public.after_microblog_insert();
        ');

        DB::unprepared('
            CREATE OR REPLACE FUNCTION public.after_microblog_update() RETURNS trigger LANGUAGE plpgsql AS $$
            BEGIN
                IF (NEW.parent_id IS NULL AND NEW.user_id IS NOT NULL) THEN
                    IF (NEW.deleted_at IS NOT NULL AND OLD.deleted_at IS NULL) THEN
                        UPDATE users SET microblog = microblog - 1 WHERE "id" = NEW.user_id;
                    ELSIF (NEW.deleted_at IS NULL AND OLD.deleted_at IS NOT NULL) THEN
                        UPDATE users SET microblog = microblog + 1 WHERE "id" = NEW.user_id;			
                    END IF; 
                END IF;
            
                RETURN NEW;
            END;$$
        ');

        DB::unprepared('
            CREATE TRIGGER after_microblog_update
              AFTER UPDATE
              ON public.microblogs
              FOR EACH ROW
              EXECUTE PROCEDURE public.after_microblog_update();
        ');

        DB::unprepared('
            UPDATE users u SET microblog = (
                SELECT COUNT(*) FROM microblogs m WHERE m.user_id = u.id AND m.parent_id IS NULL AND m.deleted_at IS NULL
            )
        ');
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::unprepared('DROP TRIGGER after_microblog_insert ON public.microblogs;');
        DB::unprepared('DROP FUNCTION public.after_microblog_insert();');
        DB::unprepared('DROP TRIGGER after_microblog_update ON public.microblogs;');
        DB::unprepared('DROP FUNCTION public.after_microblog_update();');
        DB::unprepared('ALTER TABLE public.users DROP COLUMN microblog;');
    }
}
