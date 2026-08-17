<?php

declare(strict_types=1);

namespace rguezque\ErrorHandling;

use Throwable;

/**
 * Funciona como un _Facade_ de `FileErrorLogger`, para utilizar en los bloques `try-catch` 
 * y tener un registro de errores en el `.log` del proyecto.
 */
class Logger
{
    private static ErrorHandlerConfig $config;
    private static FileErrorLogger $logger;

    /**
     * Configura el logger manual para el proyecto. Se usa en bloques `try-catch`.
     * Siempre debe ejecutarse primero, de lo contrario al llamar `Environment::logError` 
     * la excepción se enviará silenciosamente al logger de PHP para no romper el flujo de procesos.
     * 
     * @param ErrorHandlerConfig $config Configuración del logger.
     */
    public static function configure(ErrorHandlerConfig $config): void
    {
        self::$config = $config;
        self::$logger = new FileErrorLogger;
    }

    /**
     * Registra un error en el archivo `.log` del proyecto, previamente configurado.
     * Si no se encuentra una configuración, se registrará en el logger de PHP.
     * 
     * @param Throwable $exception La exception a registrar en el `.log` local.
     */
    public static function logError(Throwable $exception): void
    {
        if (!isset(self::$config)) {
            $message = sprintf(
                'A configuration for logging project errors was not found. First assign it with Logger::configure. Last exception caught: [%s] %s in %s, on line %d', 
                get_debug_type($exception), 
                $exception->getMessage(), 
                $exception->getFile(), 
                $exception->getLine()
            );
            error_log($message);
            return;
        }

        self::$logger->log($exception, self::$config);
    }
}
