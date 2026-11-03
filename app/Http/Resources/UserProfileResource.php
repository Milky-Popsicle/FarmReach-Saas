<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin User
 */
class UserProfileResource extends JsonResource
{
    public function __construct(User $resource, private readonly bool $includePrivateFields)
    {
        parent::__construct($resource);
    }

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $data = [
            'id' => $this->id,
            'name' => $this->name,
            'role' => $this->role,
            'bio' => $this->bio,
            'avatar_path' => $this->avatar_path,
        ];

        // A farmer's location is already public on the marketplace. Buyer
        // addresses stay private unless the viewer owns the profile or is an
        // administrator.
        if ($this->role === 'farmer') {
            $data['address'] = $this->address;
        }

        if ($this->includePrivateFields) {
            $data['email'] = $this->email;
            $data['phone_number'] = $this->phone_number;
            $data['gender'] = $this->gender;
            $data['birthday'] = $this->birthday?->format('Y-m-d');
            $data['address'] = $this->address;
        }

        return $data;
    }
}
