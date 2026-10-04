<?php

namespace Crater\Services\Data;

use RuntimeException;

/** Mensajes propios, sin salida del cliente SQL ni secretos del proveedor. */
class BackupOperationException extends RuntimeException
{
}
