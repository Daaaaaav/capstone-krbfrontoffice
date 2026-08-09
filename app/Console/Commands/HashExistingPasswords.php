<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class HashExistingPasswords extends Command
{
    protected $signature = 'users:hash-passwords';

    protected $description = 'Hash existing plain text passwords in the database';

    public function handle()
    {
        $users = User::all();

        $count = 0;
        foreach ($users as $user) {
            if (!str_starts_with($user->password, '$2y$')) {
                $plainPassword = $user->password;
                $user->password = Hash::make($plainPassword);
                $user->save();
                
                $this->info("Hashed password for user: {$user->email}");
                $count++;
            }
        }

        if ($count > 0) {
            $this->info("Successfully converted {$count} password(s) to bcrypt.");
        } else {
            $this->info('All passwords are already hashed.');
        }

        return 0;
    }
}
