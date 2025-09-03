<button id="enable-draggable" class="btn btn-primary">
    <i class="la la-sort me-1"></i> {{ trans('backpack::crud.sort') }}
</button>
<form id="sortForm" action="" method="post" class="d-inline">
    @csrf
    <input id="newSortOrder" name="newSortOrder" type="hidden" value="" />
    <button id="cancel-draggable" class="btn btn-outline-secondary text-decoration-none" type="button" style="display:none;">
        <i class="la la-ban me-1"></i> {{ trans('backpack::crud.cancel') }}
    </button>
    <button id="save-draggable" class="btn btn-success" type="button" style="display:none;pointer-events:none;opacity:0.5;">
        <i class="la la-save me-1"></i> {{ trans('backpack::crud.save') }}
    </button>
</form>