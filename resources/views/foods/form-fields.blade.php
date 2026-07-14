<div class="row g-3">
  <div class="col-md-6">
    <label class="form-label">Name</label>
    <input name="name" value="{{ old('name', $food->name ?? '') }}" class="form-control" required />
    @error('name')<div class="text-danger small">{{ $message }}</div>@enderror
  </div>
  <div class="col-md-6">
    <label class="form-label">Portion</label>
    <input name="portion" value="{{ old('portion', $food->portion ?? '') }}" class="form-control" />
  </div>
  <div class="col-12">
    <label class="form-label">Recipe (optional)</label>
    <textarea name="recipe" class="form-control" rows="3" placeholder="Ingredients and steps">{{ old('recipe', $food->recipe ?? '') }}</textarea>
  </div>
</div>
