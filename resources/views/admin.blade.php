<!doctype html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Field Tracker Admin</title>
    @php
      $manifestPath = public_path('build-admin/.vite/manifest.json');
      $manifest = file_exists($manifestPath) ? json_decode(file_get_contents($manifestPath), true) : null;
      $entry = $manifest['src/main.jsx'] ?? null;
    @endphp
    @if($entry && !empty($entry['css']))
      @foreach($entry['css'] as $css)
        <link rel="stylesheet" href="{{ asset('build-admin/' . $css) }}" />
      @endforeach
    @endif
  </head>
  <body>
    <div id="root"></div>
    @if($entry)
      <script type="module" src="{{ asset('build-admin/' . $entry['file']) }}"></script>
    @else
      <script type="module" src="http://localhost:5174/src/main.jsx"></script>
    @endif
  </body>
</html>
