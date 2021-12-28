<?php
declare( strict_types=1 );

use Phinx\Seed\AbstractSeed;

/**
 * Class UserSeeder
 */
final class UserSeeder extends AbstractSeed
{
    /**
     *
     */
    public function run(): void
    {
        try {
            $options = ['cost' => 12];
            
            $data = [
                [
                    'username' => 'jpruiz114',
                    'password' => password_hash( 'secret', PASSWORD_DEFAULT, $options ),
                    'email' => 'jpruiz114@gmail.com'
                ]
            ];

            $item = $this->table( 'users' );
            $item->insert( $data )->saveData();
        } catch ( Exception $exception ) {
            echo $exception->getMessage();
        }
    }
}
