<?php
declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Class CreateUsersTable
 */
final class CreateUsersTable extends AbstractMigration
{
    /**
     * The type of field used for storing passwords in databases should be varchar(255) for future-proof algorithm changes.
     */
    public function up(): void
    {
        try {
            $table = $this->table( "users", ["id" => true] );
            $table
                ->addColumn( "username", "text", ["length" => 255, "null" => false] )
                ->addColumn( "password", "text", ["length" => 255, "null" => false] )
                ->addColumn( "email", "text", ["length" => 255, "null" => false] )
                ->save();
        } catch ( Exception $exception ) {
            echo $exception->getMessage();
        }
    }
}
