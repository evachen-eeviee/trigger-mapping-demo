<?php

namespace App\Triggers;

use Talleu\TriggerMapping\Contract\PostgreSQLTriggerInterface;

class GladiatorBeforeInsert implements PostgreSQLTriggerInterface
{
    public static function getFunction(): string
    {
        return <<<SQL
        CREATE OR REPLACE FUNCTION fn_gladiator_before_insert()
        RETURNS TRIGGER AS $$
        BEGIN
            -- Le PHP ne gère plus les PV à la création. PostgreSQL calcule tout : (Pourcentage HP * 3)
            NEW.health_points := NEW.stat_hp_percent * 3;
            NEW.status := 'alive';
            NEW.action_count := 0;
            RETURN NEW;
        END;
        $$ LANGUAGE plpgsql;
        SQL;
    }

    public static function getTrigger(): string
    {
        return <<<SQL
        CREATE TRIGGER trg_gladiator_before_insert
        BEFORE INSERT ON gladiator
        FOR EACH ROW
        EXECUTE FUNCTION fn_gladiator_before_insert();
        SQL;
    }
}
