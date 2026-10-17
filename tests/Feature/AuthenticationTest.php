<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Agent;
use App\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_can_login_with_remember_me_checked(): void
    {
        $customer = Customer::create([
            'name' => 'John Customer',
            'email' => 'john@test.com',
            'password' => bcrypt('password'),
        ]);

        // Submit with browser's default checkbox value "on"
        $response = $this->post(route('customer.login.submit'), [
            'email' => 'john@test.com',
            'password' => 'password',
            'remember' => 'on',
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect(route('customer.dashboard'));
        $this->assertAuthenticatedAs($customer, 'customer');
    }

    public function test_agent_can_login_with_remember_me_checked(): void
    {
        $agent = Agent::create([
            'name' => 'Jane Agent',
            'email' => 'jane@test.com',
            'password' => bcrypt('password'),
            'is_available' => true,
        ]);

        // Submit with browser's default checkbox value "on"
        $response = $this->post(route('agent.login.submit'), [
            'email' => 'jane@test.com',
            'password' => 'password',
            'remember' => 'on',
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect(route('agent.dashboard'));
        $this->assertAuthenticatedAs($agent, 'agent');
    }

    public function test_admin_can_login_with_remember_me_checked(): void
    {
        $admin = Admin::create([
            'name' => 'Jack Admin',
            'email' => 'admin@test.com',
            'password' => bcrypt('password'),
        ]);

        // Submit with browser's default checkbox value "on"
        $response = $this->post(route('admin.login.submit'), [
            'email' => 'admin@test.com',
            'password' => 'password',
            'remember' => 'on',
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticatedAs($admin, 'admin');
    }

    public function test_login_with_value_one_also_succeeds(): void
    {
        $customer = Customer::create([
            'name' => 'Alice Customer',
            'email' => 'alice@test.com',
            'password' => bcrypt('password'),
        ]);

        $response = $this->post(route('customer.login.submit'), [
            'email' => 'alice@test.com',
            'password' => 'password',
            'remember' => '1',
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect(route('customer.dashboard'));
        $this->assertAuthenticatedAs($customer, 'customer');
    }

    public function test_invalid_credentials_show_error_message(): void
    {
        $response = $this->post(route('customer.login.submit'), [
            'email' => 'nonexistent@test.com',
            'password' => 'wrong-password',
            'remember' => 'on',
        ]);

        $response->assertSessionHasErrors(['email']);
    }
}
