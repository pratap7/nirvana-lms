<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Preview - {{ $course->name }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-stone-50 text-stone-800 antialiased">
    <div class="bg-amber-600 text-white px-4 py-2 text-sm text-center font-medium shadow">
        <span>👁️ Student Preview Mode — You are previewing <strong>{{ $course->name }}</strong> as an enrolled student.</span>
    </div>

    <div class="max-w-5xl mx-auto px-4 py-8">
        <div class="bg-white rounded-2xl shadow-sm border border-stone-200 overflow-hidden mb-8">
            <div class="p-8">
                <div class="flex items-center gap-2 mb-3">
                    <span class="px-2.5 py-1 text-xs font-semibold uppercase tracking-wider rounded-full bg-emerald-100 text-emerald-800">
                        {{ $course->default_delivery_mode->label() }}
                    </span>
                    <span class="px-2.5 py-1 text-xs font-semibold uppercase tracking-wider rounded-full bg-amber-100 text-amber-800">
                        {{ $course->difficulty_level->label() }}
                    </span>
                    @if($course->category)
                        <span class="text-xs text-stone-500 font-medium">in {{ $course->category->full_path }}</span>
                    @endif
                </div>

                <h1 class="text-3xl font-bold tracking-tight text-stone-900 mb-4">{{ $course->name }}</h1>
                <p class="text-stone-600 leading-relaxed text-lg mb-6">{{ $course->description }}</p>

                @if(!empty($course->outcomes))
                    <div class="bg-stone-50 rounded-xl p-6 border border-stone-100 mb-6">
                        <h3 class="font-semibold text-stone-900 mb-3 text-sm uppercase tracking-wider">What You Will Learn</h3>
                        <ul class="grid grid-cols-1 md:grid-cols-2 gap-2 text-sm text-stone-700">
                            @foreach($course->outcomes as $outcome)
                                <li class="flex items-start gap-2">
                                    <span class="text-emerald-600 font-bold">✓</span>
                                    <span>{{ $outcome }}</span>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </div>
        </div>

        <!-- Curriculum -->
        <div class="space-y-6">
            <h2 class="text-2xl font-bold text-stone-900">Curriculum</h2>
            @forelse($course->modules as $module)
                <div class="bg-white rounded-xl border border-stone-200 shadow-sm overflow-hidden">
                    <div class="px-6 py-4 bg-stone-100/60 border-b border-stone-200 flex justify-between items-center">
                        <h3 class="font-bold text-stone-800">{{ $module->name }}</h3>
                        <span class="text-xs text-stone-500">{{ $module->lessons->count() }} lessons</span>
                    </div>
                    <ul class="divide-y divide-stone-100">
                        @forelse($module->lessons as $lesson)
                            <li class="px-6 py-4 flex items-center justify-between hover:bg-stone-50 transition">
                                <div class="flex items-center gap-3">
                                    <span class="w-8 h-8 rounded-lg bg-stone-100 flex items-center justify-center text-xs font-bold text-stone-600">
                                        {{ $loop->iteration }}
                                    </span>
                                    <div>
                                        <h4 class="font-medium text-stone-900">{{ $lesson->title }}</h4>
                                        <div class="flex items-center gap-2 text-xs text-stone-500">
                                            <span>{{ $lesson->content_type->label() }}</span>
                                            @if($lesson->duration_minutes)
                                                <span>•</span>
                                                <span>{{ $lesson->duration_minutes }} mins</span>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                                @if($lesson->is_free_preview)
                                    <span class="px-2 py-0.5 text-xs font-medium bg-emerald-50 text-emerald-700 rounded border border-emerald-200">
                                        Free Preview
                                    </span>
                                @endif
                            </li>
                        @empty
                            <li class="px-6 py-4 text-stone-500 text-sm italic">No lessons in this module yet.</li>
                        @endforelse
                    </ul>
                </div>
            @empty
                <div class="bg-white rounded-xl border border-stone-200 p-8 text-center text-stone-500">
                    No curriculum modules created for this course yet.
                </div>
            @endforelse
        </div>
    </div>
</body>
</html>
