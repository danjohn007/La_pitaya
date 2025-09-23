# Solución al problema de generación de tickets

## Problema identificado

El botón "Generar Ticket" no está insertando tickets en la base de datos porque la columna `payment_method` de la tabla `tickets` tiene una restricción ENUM que solo permite:
- `'efectivo'`
- `'tarjeta'` 
- `'transferencia'`

Pero el formulario web permite seleccionar también:
- `'intercambio'`
- `'pendiente_por_cobrar'`

Cuando se intenta insertar un ticket con estos métodos de pago adicionales, la base de datos rechaza la inserción.

## Solución

Ejecutar la migración existente que actualiza la restricción ENUM para incluir todos los métodos de pago.

### Paso 1: Aplicar migración de base de datos

```bash
mysql -u exhacien_restaurante -p exhacien_restaurante < database/migration_payment_methods.sql
```

### Paso 2: Verificar que la migración se aplicó correctamente

```sql
USE exhacien_restaurante;
DESCRIBE tickets;
```

La columna `payment_method` debe mostrar:
```
enum('efectivo','tarjeta','transferencia','intercambio','pendiente_por_cobrar')
```

## Contenido de la migración necesaria

La migración que debe ejecutarse es:

```sql
-- Migration to add missing payment methods to tickets table
-- This fixes the ENUM constraint to allow 'intercambio' and 'pendiente_por_cobrar' payment methods

USE exhacien_restaurante;

-- Alter tickets table to include new payment methods
ALTER TABLE tickets 
MODIFY COLUMN payment_method ENUM('efectivo', 'tarjeta', 'transferencia', 'intercambio', 'pendiente_por_cobrar') DEFAULT 'efectivo';

-- Commit the changes
COMMIT;
```

## Validación del fix

Una vez aplicada la migración, los tickets se podrán generar correctamente con cualquier método de pago disponible en el formulario.