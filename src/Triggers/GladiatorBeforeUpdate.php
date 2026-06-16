<?php

namespace App\Triggers;

use Talleu\TriggerMapping\Contract\PostgreSQLTriggerInterface;

class GladiatorBeforeUpdate implements PostgreSQLTriggerInterface
{
    public static function getFunction(): string
    {
        return <<<SQL
        CREATE OR REPLACE FUNCTION fn_update_gladiator_status()
        RETURNS TRIGGER AS $$
        BEGIN
            IF NEW.health_points <= 1 THEN
                NEW.status := 'exhausted';
                NEW.health_points := 1;
            ELSIF NEW.health_points > 1 THEN
                NEW.status := 'alive';
            END IF;

            RETURN NEW;
        END;
        $$ LANGUAGE plpgsql;
        SQL;
    }

    public static function getTrigger(): string
    {
        return <<<SQL
        CREATE TRIGGER trg_gladiator_status_update
        BEFORE UPDATE ON gladiator
        FOR EACH ROW
        EXECUTE FUNCTION fn_update_gladiator_status();
        SQL;
    }
}
