{{-- The command to run, with a copy button. Composer from backend/, pnpm from frontend/. --}}
<td class="small">
    @if (! empty($command))
        <div class="d-flex align-items-center gap-1">
            <code class="text-nowrap">{{ $command }}</code>
            <button type="button" class="btn btn-sm btn-ghost-secondary px-1 py-0" data-copy-command="{{ $command }}" title="Copia il comando">
                <i class="la la-copy"></i>
            </button>
        </div>
        <div class="text-muted">da <code>{{ str_starts_with($command, 'pnpm') ? 'frontend/' : 'backend/' }}</code></div>
    @else
        <span class="text-muted">—</span>
    @endif
</td>
