<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_screen_can_be_rendered(): void
    {
        $response = $this->get('/register');

        $response->assertStatus(200);
    }

    public function test_new_users_can_register(): void
    {
        // assignRole('buyer') için roller mevcut olmalı
        $this->seed(\Database\Seeders\RoleSeeder::class);

        // Uygulamanın gerçek kayıt akışı: username, phone ve role (buyer/seller) zorunlu.
        $response = $this->post('/register', [
            'name' => 'Test User',
            'username' => 'testuser',
            'email' => 'test@example.com',
            'phone' => '5551234567',
            'role' => 'buyer',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $this->assertAuthenticated();
        // Kayıt sonrası e-posta doğrulama ekranına yönlendiriliyor.
        $response->assertRedirect(route('verification.notice'));
    }
}
