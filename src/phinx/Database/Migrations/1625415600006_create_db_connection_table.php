<?php declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Class CreateDbConnectionTable
 */
final class CreateDbConnectionTable extends AbstractMigration
{
    /**
     *
     */
    public function up(): void
    {
        try {
            $table = $this->table( 'db_connection', ['id' => true] );
            $table
                ->addColumn( 'driver_id', 'integer' )
                ->addColumn( 'username', 'text', ['length' => 255, 'null' => true] )
                ->addColumn( 'password', 'text', ['length' => 255, 'null' => true] )
                ->addColumn( 'host', 'text', ['length' => 255, 'null' => true] )
                ->addColumn( 'port', 'integer', ['null' => true] )
                ->addColumn( 'db_name', 'text', ['length' => 255, 'null' => true] )
                ->addColumn( 'unix_socket', 'text', ['length' => 255, 'null' => true] )
                ->addColumn( 'charset', 'text', ['length' => 255, 'null' => true] )
                ->save();

            $table->addForeignKey( 'driver_id', 'db_connection_type', 'id' )->save();
        } catch ( Exception $exception ) {
            echo $exception->getMessage();
        }
    }
}
