<?php

namespace App\Services;

use App\Models\User;

/** Builds a nested downline tree with one query per level. */
class GenealogyTree
{
    /**
     * @return array{user: User, children: array, count: int, more: bool}
     */
    public function build(User $root, int $depth = 4): array
    {
        $byParent = [];
        $parentIds = [$root->id];
        $lastLevelIds = [];

        for ($level = 1; $level <= $depth && $parentIds; $level++) {
            $children = User::whereIn('sponsor_id', $parentIds)
                ->select('id', 'name', 'username', 'sponsor_id', 'status', 'paid_amount', 'avatar_path', 'created_at')
                ->orderBy('id')
                ->get();

            foreach ($children as $child) {
                $byParent[$child->sponsor_id][] = $child;
            }
            $parentIds = $children->pluck('id')->all();
            $lastLevelIds = $parentIds;
        }

        // Which deepest-level nodes still have children (shown as "view more").
        $hasMore = $lastLevelIds
            ? array_flip(User::whereIn('sponsor_id', $lastLevelIds)->distinct()->pluck('sponsor_id')->all())
            : [];

        return $this->node($root, $byParent, $hasMore);
    }

    private function node(User $user, array $byParent, array $hasMore): array
    {
        $children = array_map(fn (User $child) => $this->node($child, $byParent, $hasMore), $byParent[$user->id] ?? []);

        return [
            'user' => $user,
            'children' => $children,
            'count' => count($children),
            'more' => ! $children && isset($hasMore[$user->id]),
        ];
    }
}
