@if(session()->has('impersonated_by'))
    <div style="background: linear-gradient(90deg, #b45309, #d97706); color: #ffffff; padding: 0.5rem 1rem; display: flex; align-items: center; justify-content: space-between; font-size: 0.875rem; z-index: 9999; position: sticky; top: 0; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.2);">
        <div style="display: flex; align-items: center; gap: 0.5rem;">
            <svg style="width: 1.25rem; height: 1.25rem;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
            </svg>
            <span>
                <strong>Admin Impersonation Mode:</strong> Viewing as <strong>{{ auth()->user()?->full_name }}</strong> ({{ auth()->user()?->email }})
            </span>
        </div>
        <form method="POST" action="{{ route('impersonation.stop') }}" style="margin: 0;">
            @csrf
            <button type="submit" style="background: rgba(0, 0, 0, 0.4); border: 1px solid rgba(255, 255, 255, 0.3); color: #ffffff; padding: 0.25rem 0.75rem; border-radius: 0.375rem; font-weight: 600; cursor: pointer; transition: all 0.2s;">
                Exit Impersonation
            </button>
        </form>
    </div>
@endif
