@extends('layouts.admin')
@section('title', 'New checklist')

@section('content')
@include('admin.partials._page')

<div class="page-head"><div><h1>New checklist</h1><p>You add the fields on the next page.</p></div></div>

<form method="POST" action="{{ route('admin.checklists.store') }}" class="card pad" style="max-width:560px">
  @csrf
  <div class="field">
    <label for="name">Name</label>
    <input id="name" name="name" class="inp" style="width:100%" maxlength="180" required value="{{ old('name') }}">
    @error('name')<div class="err">{{ $message }}</div>@enderror
  </div>
  <div class="field">
    <label for="description">Description</label>
    <textarea id="description" name="description" class="txta" maxlength="500">{{ old('description') }}</textarea>
  </div>
  <button class="btn btn-primary">Create</button>
  <a href="{{ route('admin.checklists.index') }}" class="btn btn-ghost">Cancel</a>
</form>
@endsection
