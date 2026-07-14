<?php

namespace Tests\Feature\Authorization;

use App\Models\Branch;
use App\Models\Member;
use App\Models\User;
use App\Models\UserBranchScope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Covers 06_TESTING.md §6 (AUTH-02/03/04) and the Cabang Scope resolution
 * logic itself (User::allowedBranchIds() / UserBranchScope::allowedBranchIds()).
 *
 * Regression guard: allowedBranchIds() must return `null` for "All Branch"
 * scope and NOT collapse it to `[]` — a `??`-operator bug previously made
 * "All Branch" behave as "zero branches" because it couldn't distinguish
 * "no scope row" from "scope row present but its value is legitimately null".
 */
class BranchScopeTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_without_any_scope_row_is_restricted_to_nothing(): void
    {
        $user = User::factory()->create();

        $this->assertSame([], $user->allowedBranchIds());
    }

    public function test_all_branch_scope_returns_null_meaning_unrestricted(): void
    {
        $user = User::factory()->create();
        UserBranchScope::query()->create(['user_id' => $user->id, 'scope_type' => 'all']);

        $this->assertNull($user->fresh()->allowedBranchIds());
    }

    public function test_single_branch_scope_returns_that_one_branch_id(): void
    {
        $user = User::factory()->create();
        $branch = Branch::factory()->create();

        UserBranchScope::query()->create([
            'user_id' => $user->id,
            'scope_type' => 'single',
            'single_branch_id' => $branch->id,
        ]);

        $this->assertEquals([$branch->id], $user->fresh()->allowedBranchIds());
    }

    public function test_multiple_branch_scope_returns_the_assigned_branch_ids(): void
    {
        $user = User::factory()->create();
        $branchA = Branch::factory()->create();
        $branchB = Branch::factory()->create();
        Branch::factory()->create(); // a third branch NOT assigned to this user

        $scope = UserBranchScope::query()->create(['user_id' => $user->id, 'scope_type' => 'multiple']);
        $scope->branches()->attach([$branchA->id, $branchB->id]);

        $this->assertEqualsCanonicalizing([$branchA->id, $branchB->id], $user->fresh()->allowedBranchIds());
    }

    /** AUTH-02: a Single-Branch user cannot see a Member belonging to another branch. */
    public function test_single_branch_user_cannot_query_members_of_another_branch(): void
    {
        $user = User::factory()->create();
        $ownBranch = Branch::factory()->create();
        $otherBranch = Branch::factory()->create();

        UserBranchScope::query()->create([
            'user_id' => $user->id,
            'scope_type' => 'single',
            'single_branch_id' => $ownBranch->id,
        ]);

        $memberInOwnBranch = Member::factory()->create(['branch_id' => $ownBranch->id]);
        $memberInOtherBranch = Member::factory()->create(['branch_id' => $otherBranch->id]);

        $this->actingAs($user);

        $this->assertNotNull(Member::find($memberInOwnBranch->id));
        $this->assertNull(Member::find($memberInOtherBranch->id));
    }

    /** AUTH-04: an All-Branch user can see members across every branch. */
    public function test_all_branch_user_can_query_members_of_every_branch(): void
    {
        $user = User::factory()->create();
        UserBranchScope::query()->create(['user_id' => $user->id, 'scope_type' => 'all']);

        $memberA = Member::factory()->create();
        $memberB = Member::factory()->create();

        $this->actingAs($user);

        $this->assertNotNull(Member::find($memberA->id));
        $this->assertNotNull(Member::find($memberB->id));
    }
}
