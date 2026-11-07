<!doctype html>
<html>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Raise a request — {{ \App\Models\Tenant::current()->name }}</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;700&family=Figtree:wght@600;700;800&display=swap" rel="stylesheet">
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{background:#FBFAF6;color:#1B1A24;font-family:'DM Sans',system-ui,sans-serif;font-size:14px;line-height:1.5}
.wrap{max-width:560px;margin:0 auto;padding:28px 20px 60px}
.back{color:#5F5D6E;font-size:13px;text-decoration:none;font-weight:600;display:inline-block;margin-bottom:14px}
h1{font-family:'Figtree',sans-serif;font-size:22px;font-weight:800;margin-bottom:20px}
.card{background:#fff;border:1px solid #E6E4EE;border-radius:14px;padding:22px}
.field{margin-bottom:16px}
label{display:block;font-weight:700;font-size:13px;margin-bottom:6px}
select,textarea,input[type=file]{width:100%;padding:10px 12px;border:1px solid #E6E4EE;border-radius:10px;font:inherit;font-size:13.5px}
textarea{resize:vertical}
.err{color:#C2381B;font-size:12px;margin-top:4px}
.btn{display:inline-block;background:#FFC61A;color:#1B1A24;font-weight:700;padding:11px 22px;border-radius:999px;border:0;cursor:pointer;font-size:13.5px;font-family:inherit}
</style>
</head>
<body>
<div class="wrap">
    <a href="{{ route('portal.requests.index') }}" class="back">← Your requests</a>
    <h1>Raise a new request</h1>

    <div class="card">
        <form method="POST" action="{{ route('portal.requests.raise.store') }}" enctype="multipart/form-data">
            @csrf

            <div class="field">
                <label>Site</label>
                <select name="project_site_id" required>
                    <option value="">Select a site</option>
                    @foreach ($sites as $site)
                        <option value="{{ $site->id }}" {{ old('project_site_id') == $site->id ? 'selected' : '' }}>{{ $site->name }}</option>
                    @endforeach
                </select>
                @error('project_site_id')<div class="err">{{ $message }}</div>@enderror
            </div>

            <div class="field">
                <label>Category</label>
                <select name="service_category_id" required>
                    <option value="">Select a category</option>
                    @foreach ($categories as $cat)
                        <option value="{{ $cat->id }}" {{ old('service_category_id') == $cat->id ? 'selected' : '' }}>{{ $cat->name }}</option>
                    @endforeach
                </select>
                @error('service_category_id')<div class="err">{{ $message }}</div>@enderror
            </div>

            <div class="field">
                <label>What's the issue?</label>
                <textarea name="description" rows="5" required>{{ old('description') }}</textarea>
                @error('description')<div class="err">{{ $message }}</div>@enderror
            </div>

            <div class="field">
                <label>Photos (optional, up to 10)</label>
                <input type="file" name="photos[]" accept="image/*" multiple>
                @error('photos')<div class="err">{{ $message }}</div>@enderror
            </div>

            <button type="submit" class="btn">Raise request</button>
        </form>
    </div>
</div>
</body>
</html>