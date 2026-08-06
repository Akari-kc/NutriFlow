<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Food;
use Illuminate\Support\Facades\Storage;

class FoodController extends Controller
{
    public function index()
    {
        $user = auth()->user();
        $foods = Food::query()
            ->when($user && $user->school_id, fn($q)=> $q->where('school_id', $user->school_id))
            ->latest()
            ->paginate(10);
        return view('foods.index', compact('foods'));
    }

    public function show(Food $food)
    {
        $user = auth()->user();
        if ($user && $user->school_id && $food->school_id !== $user->school_id) {
            abort(403);
        }
        return view('foods.show', compact('food'));
    }

    public function create()
    {
        return view('foods.create');
    }

    public function store(Request $request)
    {
        $data = $this->validateFood($request);
        $data['school_id'] = auth()->user()?->school_id;
        Food::create($data);
        return redirect()->route('menu-items.index')->with('status','Meal added to this school\'s catalog');
    }

    public function edit(Food $food)
    {
        $user = auth()->user();
        if ($user && $user->school_id && $food->school_id !== $user->school_id) {
            abort(403);
        }
        return view('foods.edit', compact('food'));
    }

    public function update(Request $request, Food $food)
    {
        $user = auth()->user();
        if ($user && $user->school_id && $food->school_id !== $user->school_id) {
            abort(403);
        }
        $data = $this->validateFood($request);
        $food->update($data);
        return redirect()->route('menu-items.index')->with('status','Meal updated');
    }

    public function destroy(Food $food)
    {
        $user = auth()->user();
        if ($user && $user->school_id && $food->school_id !== $user->school_id) {
            abort(403);
        }
        $food->delete();
        return redirect()->route('menu-items.index')->with('status','Meal removed from this school\'s catalog');
    }

    public function uploadCsv(Request $request)
    {
        $request->validate(['csv' => ['required','file','mimes:csv,txt']]);
        $path = $request->file('csv')->store('uploads');
        $handle = fopen(Storage::path($path), 'r');
        $header = fgetcsv($handle);
        while(($row = fgetcsv($handle)) !== false){
            $data = array_combine($header, $row);
            Food::create([
                'name' => $data['name'] ?? 'Unknown',
                'portion' => $data['portion'] ?? null,
                'school_id' => auth()->user()?->school_id,
            ]);
        }
        fclose($handle);
        return back()->with('status', 'Foods imported successfully');
    }

    private function validateFood(Request $request): array
    {
        return $request->validate([
            'name' => 'required|string|max:255',
            'portion' => 'nullable|string|max:100',
            'recipe' => 'nullable|string',
        ]);
    }
}
