<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\User\Services\UserService;
use App\DTOs\CreateUserDTO;
use App\Mail\WelcomeUserMail;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class CreateUserAndSendWelcome extends Command
{
    protected $signature = 'user:create-welcome \
        {name : User\'s full name} \
        {email : User\'s email address} \
        {--password= : Plain password (if omitted, a random one will be generated)} \
        {--role=therapist : Role: admin|therapist|student|parent}';

    protected $description = 'Create a user and send a welcome email with credentials (no email verification).';

    public function __construct(private readonly UserService $userService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $name = (string) $this->argument('name');
        $email = (string) $this->argument('email');
        $password = (string) ($this->option('password') ?? str()->random(12));
        $role = (string) $this->option('role');

        $dto = CreateUserDTO::fromArray([
            'name' => $name,
            'email' => $email,
            'password' => $password,
        ]);

        // Create user using service
        $user = $this->userService->createWithRole($dto, $role);

        // Send welcome email with credentials
        Mail::to($user->email)->send(new WelcomeUserMail($user->name, $user->email, $password));

        $this->info("User created: {$user->email} (role: {$role}). A welcome email with credentials was sent.");
        $this->line("Password: {$password}");

        return self::SUCCESS;
    }
}
