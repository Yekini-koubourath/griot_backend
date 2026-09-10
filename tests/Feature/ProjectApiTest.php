<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_create_project_with_image_and_members(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/projects', [
                'name' => 'Projet test',
                'description' => 'Description du projet',
                'status' => 'Actif',
                'members' => 5,
                'image' => 'data:image/png;base64,abc123',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('project.name', 'Projet test')
            ->assertJsonPath('project.members', 5)
            ->assertJsonPath('project.image', 'data:image/png;base64,abc123');

        $this->assertDatabaseHas('projects', [
            'user_id' => $user->id,
            'name' => 'Projet test',
            'members' => 5,
            'image' => 'data:image/png;base64,abc123',
        ]);
    }
}
