<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private function definiciones(): array
    {
        return [
            'trg_pagos_proteger_update' => <<<'SQL'
                CREATE TRIGGER trg_pagos_proteger_update
                BEFORE UPDATE ON pagos
                FOR EACH ROW
                BEGIN
                    IF NOT (
                        NEW.id <=> OLD.id
                        AND NEW.sucursal_id <=> OLD.sucursal_id
                        AND NEW.referencia_tipo <=> OLD.referencia_tipo
                        AND NEW.referencia_id <=> OLD.referencia_id
                        AND NEW.pago_original_id <=> OLD.pago_original_id
                        AND NEW.recibido_por <=> OLD.recibido_por
                        AND NEW.monto_total <=> OLD.monto_total
                        AND NEW.moneda <=> OLD.moneda
                        AND NEW.created_at <=> OLD.created_at
                    ) THEN
                        SIGNAL SQLSTATE '45000'
                            SET MESSAGE_TEXT = 'Identidad financiera del pago inmutable';
                    END IF;

                    IF OLD.estado <> 'pendiente' THEN
                        IF NOT (
                            NEW.estado <=> OLD.estado
                            AND NEW.fecha_pago <=> OLD.fecha_pago
                        ) THEN
                            SIGNAL SQLSTATE '45000'
                                SET MESSAGE_TEXT = 'Pago definitivo inmutable';
                        END IF;
                    ELSE
                        IF NEW.estado NOT IN (
                            'pendiente',
                            'confirmado',
                            'cancelado'
                        ) THEN
                            SIGNAL SQLSTATE '45000'
                                SET MESSAGE_TEXT = 'Transicion de pago invalida';
                        END IF;

                        IF NEW.estado = 'confirmado'
                            AND NEW.fecha_pago IS NULL THEN
                            SIGNAL SQLSTATE '45000'
                                SET MESSAGE_TEXT = 'Confirmacion requiere fecha';
                        END IF;

                        IF NEW.estado <> 'confirmado'
                            AND NEW.fecha_pago IS NOT NULL THEN
                            SIGNAL SQLSTATE '45000'
                                SET MESSAGE_TEXT = 'Fecha de pago incompatible';
                        END IF;
                    END IF;
                END
                SQL,

            'trg_pagos_no_delete' => <<<'SQL'
                CREATE TRIGGER trg_pagos_no_delete
                BEFORE DELETE ON pagos
                FOR EACH ROW
                SIGNAL SQLSTATE '45000'
                    SET MESSAGE_TEXT = 'Los pagos no pueden eliminarse'
                SQL,

            'trg_dp_proteger_insert' => <<<'SQL'
                CREATE TRIGGER trg_dp_proteger_insert
                BEFORE INSERT ON detalle_pagos
                FOR EACH ROW
                BEGIN
                    DECLARE estado_pago VARCHAR(30);

                    SELECT estado
                    INTO estado_pago
                    FROM pagos
                    WHERE id = NEW.pago_id
                    FOR UPDATE;

                    IF estado_pago IS NULL
                        OR estado_pago <> 'pendiente' THEN
                        SIGNAL SQLSTATE '45000'
                            SET MESSAGE_TEXT = 'No se pueden agregar partidas a pagos definitivos';
                    END IF;
                END
                SQL,

            'trg_dp_proteger_update' => <<<'SQL'
                CREATE TRIGGER trg_dp_proteger_update
                BEFORE UPDATE ON detalle_pagos
                FOR EACH ROW
                BEGIN
                    DECLARE estado_pago VARCHAR(30);

                    SELECT estado
                    INTO estado_pago
                    FROM pagos
                    WHERE id = OLD.pago_id
                    FOR UPDATE;

                    IF NOT (
                        NEW.id <=> OLD.id
                        AND NEW.pago_id <=> OLD.pago_id
                        AND NEW.created_at <=> OLD.created_at
                    ) THEN
                        SIGNAL SQLSTATE '45000'
                            SET MESSAGE_TEXT = 'Identidad de partida inmutable';
                    END IF;

                    IF estado_pago <> 'pendiente' THEN
                        SIGNAL SQLSTATE '45000'
                            SET MESSAGE_TEXT = 'Partida de pago definitivo inmutable';
                    END IF;
                END
                SQL,

            'trg_dp_proteger_delete' => <<<'SQL'
                CREATE TRIGGER trg_dp_proteger_delete
                BEFORE DELETE ON detalle_pagos
                FOR EACH ROW
                BEGIN
                    DECLARE estado_pago VARCHAR(30);

                    SELECT estado
                    INTO estado_pago
                    FROM pagos
                    WHERE id = OLD.pago_id
                    FOR UPDATE;

                    IF estado_pago <> 'pendiente' THEN
                        SIGNAL SQLSTATE '45000'
                            SET MESSAGE_TEXT = 'Partida definitiva no eliminable';
                    END IF;
                END
                SQL,

            'trg_oi_proteger_update' => <<<'SQL'
                CREATE TRIGGER trg_oi_proteger_update
                BEFORE UPDATE ON operaciones_idempotentes
                FOR EACH ROW
                BEGIN
                    IF NOT (
                        NEW.id <=> OLD.id
                        AND NEW.sucursal_id <=> OLD.sucursal_id
                        AND NEW.usuario_id <=> OLD.usuario_id
                        AND NEW.tipo_operacion <=> OLD.tipo_operacion
                        AND BINARY NEW.clave <=> BINARY OLD.clave
                        AND BINARY NEW.hash_solicitud <=> BINARY OLD.hash_solicitud
                        AND NEW.created_at <=> OLD.created_at
                    ) THEN
                        SIGNAL SQLSTATE '45000'
                            SET MESSAGE_TEXT = 'Identidad idempotente inmutable';
                    END IF;

                    IF OLD.resultado_id IS NOT NULL
                        OR OLD.resultado_tipo IS NOT NULL THEN
                        SIGNAL SQLSTATE '45000'
                            SET MESSAGE_TEXT = 'Resultado idempotente definitivo';
                    END IF;

                    IF NEW.resultado_id IS NULL
                        OR NEW.resultado_id = 0
                        OR NEW.resultado_tipo IS NULL
                        OR CHAR_LENGTH(TRIM(NEW.resultado_tipo)) = 0 THEN
                        SIGNAL SQLSTATE '45000'
                            SET MESSAGE_TEXT = 'Resultado idempotente invalido';
                    END IF;
                END
                SQL,

            'trg_oi_no_delete' => <<<'SQL'
                CREATE TRIGGER trg_oi_no_delete
                BEFORE DELETE ON operaciones_idempotentes
                FOR EACH ROW
                SIGNAL SQLSTATE '45000'
                    SET MESSAGE_TEXT = 'Las operaciones idempotentes no pueden eliminarse'
                SQL,

            'trg_asf_proteger_update' => <<<'SQL'
                CREATE TRIGGER trg_asf_proteger_update
                BEFORE UPDATE ON aplicaciones_saldos_favor
                FOR EACH ROW
                BEGIN
                    IF NOT (
                        NEW.id <=> OLD.id
                        AND NEW.saldo_favor_id <=> OLD.saldo_favor_id
                        AND NEW.detalle_pago_id <=> OLD.detalle_pago_id
                        AND NEW.sucursal_id <=> OLD.sucursal_id
                        AND NEW.usuario_id <=> OLD.usuario_id
                        AND NEW.monto_aplicado <=> OLD.monto_aplicado
                        AND NEW.created_at <=> OLD.created_at
                    ) THEN
                        SIGNAL SQLSTATE '45000'
                            SET MESSAGE_TEXT = 'Identidad de aplicacion inmutable';
                    END IF;

                    IF OLD.estado <> 'aplicada'
                        OR NEW.estado <> 'revertida'
                        OR NEW.revertida_por IS NULL THEN
                        SIGNAL SQLSTATE '45000'
                            SET MESSAGE_TEXT = 'Transicion de aplicacion invalida';
                    END IF;
                END
                SQL,

            'trg_asf_no_delete' => <<<'SQL'
                CREATE TRIGGER trg_asf_no_delete
                BEFORE DELETE ON aplicaciones_saldos_favor
                FOR EACH ROW
                SIGNAL SQLSTATE '45000'
                    SET MESSAGE_TEXT = 'Las aplicaciones de saldo no pueden eliminarse'
                SQL,

            'trg_sf_proteger_update' => <<<'SQL'
                CREATE TRIGGER trg_sf_proteger_update
                BEFORE UPDATE ON saldos_favor
                FOR EACH ROW
                BEGIN
                    IF NOT (
                        NEW.id <=> OLD.id
                        AND NEW.cliente_id <=> OLD.cliente_id
                        AND NEW.sucursal_id <=> OLD.sucursal_id
                        AND NEW.usuario_id <=> OLD.usuario_id
                        AND NEW.moneda <=> OLD.moneda
                        AND NEW.created_at <=> OLD.created_at
                    ) THEN
                        SIGNAL SQLSTATE '45000'
                            SET MESSAGE_TEXT = 'Identidad de saldo a favor inmutable';
                    END IF;

                    IF OLD.estado = 'cancelado' THEN
                        SIGNAL SQLSTATE '45000'
                            SET MESSAGE_TEXT = 'Saldo cancelado definitivo';
                    END IF;

                    IF NEW.estado = 'cancelado'
                        AND (
                            OLD.estado <> 'activo'
                            OR OLD.saldo_disponible <= 0
                            OR NEW.saldo_disponible <> 0
                        ) THEN
                        SIGNAL SQLSTATE '45000'
                            SET MESSAGE_TEXT = 'Cancelacion de saldo invalida';
                    END IF;

                    IF NEW.estado = 'agotado'
                        AND NEW.saldo_disponible <> 0 THEN
                        SIGNAL SQLSTATE '45000'
                            SET MESSAGE_TEXT = 'Saldo agotado debe ser cero';
                    END IF;
                END
                SQL,

            'trg_sf_no_delete' => <<<'SQL'
                CREATE TRIGGER trg_sf_no_delete
                BEFORE DELETE ON saldos_favor
                FOR EACH ROW
                SIGNAL SQLSTATE '45000'
                    SET MESSAGE_TEXT = 'Los saldos a favor no pueden eliminarse'
                SQL,
        ];
    }

    public function up(): void
    {
        $creados = [];

        try {
            foreach ($this->definiciones() as $nombre => $sql) {
                DB::unprepared($sql);

                $creados[] = $nombre;
            }
        } catch (\Throwable $e) {
            foreach (array_reverse($creados) as $nombre) {
                DB::unprepared(
                    "DROP TRIGGER IF EXISTS `{$nombre}`"
                );
            }

            throw $e;
        }
    }

    public function down(): void
    {
        foreach (
            array_reverse(array_keys($this->definiciones()))
            as $nombre
        ) {
            DB::unprepared(
                "DROP TRIGGER IF EXISTS `{$nombre}`"
            );
        }
    }
};
