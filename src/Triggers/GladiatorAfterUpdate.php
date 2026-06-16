<?php

namespace App\Triggers;

use Talleu\TriggerMapping\Contract\PostgreSQLTriggerInterface;

class GladiatorAfterUpdate implements PostgreSQLTriggerInterface
{
    public static function getFunction(): string
    {
        return <<<SQL
        CREATE OR REPLACE FUNCTION fn_gladiator_after_update()
        RETURNS TRIGGER AS $$
        BEGIN
            IF OLD.status = 'alive' AND NEW.status = 'exhausted' THEN
                INSERT INTO match_log (gladiator_name, action, created_at)
                VALUES (NEW.name, 'S''est effondré dans l''arène.', NOW());
            END IF;

            RETURN NEW;
        END;
        $$ LANGUAGE plpgsql;
        SQL;
    }

    public static function getTrigger(): string
    {
        return <<<SQL
        CREATE TRIGGER trg_gladiator_after_update
        AFTER UPDATE ON gladiator
        FOR EACH ROW
        EXECUTE FUNCTION fn_gladiator_after_update();
        SQL;
    }
}
