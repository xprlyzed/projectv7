<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_page_is_displayed(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->get('/profile');

        $response->assertOk();
    }

    public function test_profile_information_can_be_updated(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->put('/profile', [
                'name' => 'Yeni Ad',
                'username' => 'yeniad'.$user->id,
                'phone' => '5551112233',
            ]);

        $response->assertSessionHasNoErrors();

        $user->refresh();

        $this->assertSame('Yeni Ad', $user->name);
        $this->assertSame('yeniad'.$user->id, $user->username);
    }

    public function test_user_can_delete_their_account(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->delete('/profile', [
                'delete_password' => 'password',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/');

        $this->assertGuest();
        // User modeli artık SoftDeletes kullanıyor → kayıt tamamen silinmez, deleted_at işaretlenir.
        $this->assertSoftDeleted('users', ['id' => $user->id]);
    }

    public function test_correct_password_must_be_provided_to_delete_account(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->from('/profile')
            ->delete('/profile', [
                'delete_password' => 'wrong-password',
            ]);

        $response
            ->assertSessionHasErrors('delete_password')
            ->assertRedirect('/profile');

        $this->assertNotNull($user->fresh());
    }
}
