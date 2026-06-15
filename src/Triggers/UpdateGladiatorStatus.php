<?php

namespace App\Triggers;

// Correction du namespace ici : Contract au lieu de Trigger
use Talleu\TriggerMapping\Contract\PostgreSQLTriggerInterface;

class UpdateGladiatorStatus implements PostgreSQLTriggerInterface
{
    public static function getFunction(): string
    {
        return <<<SQL
        CREATE OR REPLACE FUNCTION fn_update_gladiator_status()
        RETURNS TRIGGER AS $$
        BEGIN
            IF NEW.health_points <= 0 THEN
                NEW.status := 'dead';
                NEW.health_points := 0;
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
