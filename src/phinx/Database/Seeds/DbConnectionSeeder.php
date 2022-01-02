<?php declare(strict_types=1);

use Coco\SourceWatcherApi\Database\DbConnectionTypeDAO;
use Phinx\Seed\AbstractSeed;

/**
 * Class DbConnectionSeeder
 */
final class DbConnectionSeeder extends AbstractSeed
{
    /**
     *
     */
    public function run(): void
    {
        try {
            $mysql_driver_id = 0;

            $dbConnectionTypeDAO = new DbConnectionTypeDAO();
            $connectionTypes = $dbConnectionTypeDAO->getDbConnectionType();

            foreach ( $connectionTypes as $connectionType ) {
                if ( $connectionType->getDriver() === 'mysql' ) {
                    $mysql_driver_id = $connectionType->getId();
                    break;
                }
            }

            $data = [
                [
                    'driver_id' => $mysql_driver_id,
                    'username' => 'ak2skzo76wdf5kz8',
                    'password' => 'knp41o6yhqvz63tt',
                    'host' => 'ble5mmo2o5v9oouq.cbetxkdyhwsb.us-east-1.rds.amazonaws.com',
                    'port' => 3306,
                    'db_name' => 'nalg5bks8jd05r8f'
                ]
            ];

            $item = $this->table( 'db_connection' );
            $item->insert( $data )->saveData();
        } catch ( Exception $exception ) {
            echo $exception->getMessage();
        }
    }
}
