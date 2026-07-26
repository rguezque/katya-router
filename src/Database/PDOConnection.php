<?php

declare(strict_types=1);

namespace rguezque\Database;

use Closure;
use PDO;
use Throwable;

/**
 * The DatabaseConnection class extends PDO to add custom functionality. 
 * By extending PDO, it maintains full native compatibility but allows 
 * you to add methods like `transactional()` directly to the instance.
 * 
 * @method mixed transactional(Closure $callback) Executes a function/closure within a database transaction.
 */
class PDOConnection extends PDO
{
    /**
     * Executes a function/closure within a database transaction. By default, 
     * if a specific connection is not defined in the second argument, it will try 
     * to connect with the default Singleton connection (`BdConnection::autoConnect`).
     * 
     * * If the function executes without errors, a commit is performed.
     * * If the function throws an exception or error, a rollback is performed and the exception is rethrown.
     * 
     * @param Closure $callback The function to execute. It receives this same instance as an argument
     * @return mixed The value returned by the executed callback
     * @throws Throwable If the function launched within the transaction fails
     */
    public function transactional(Closure $callback): mixed
    {
        $this->beginTransaction();

        try {
            $result = $callback($this);
            $this->commit();
        } catch (Throwable $e) {
            $this->rollBack();
            throw $e;
        }

        return $result;
    }
}
