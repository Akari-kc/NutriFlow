<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class School extends Model
{
	use HasFactory;

	protected $fillable = [
		'name', 'address', 'street', 'city', 'region',
	];

	public function students(): HasMany
	{
		return $this->hasMany(Student::class);
	}

	public function users(): HasMany
	{
		return $this->hasMany(User::class);
	}

	public function foods(): HasMany
	{
		return $this->hasMany(Food::class);
	}

	protected static function booted(): void
	{
		static::deleting(function (School $school) {
			DB::transaction(function () use ($school) {
				// Delete aide users under this school (so their emails can be reused)
				User::where('school_id', $school->id)->where('role', 'aide')->delete();

				// Delete foods belonging to this school (meal_items will cascade on delete)
				$school->foods()->delete();

				// Delete students (meals and meal_items will cascade via FKs)
				$school->students()->each(function (Student $s) {
					$s->delete();
				});
			});
		});
	}
}

