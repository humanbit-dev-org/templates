<div class="card">
    <div class="documentation card-body">
  
      <h3>{!! trans('backpack::tutorial.getting_started') !!}</h3>
      <p>{!! trans('backpack::tutorial.getting_started_message') !!}</p>
  
      <div id="accordionTutorial" role="tablist">
        <div id="step1" class="step card mb-1">
          <div class="card-header bg-light" id="headingStep1" role="tab">
          <h5 class="documentation_items mb-0 w-100 d-flex align-items-center justify-content-between">
            <a data-bs-toggle="collapse" href="#collapseStep1" aria-expanded="false" aria-controls="collapseStep1" class="collapsed">
              <span class="badge badge_button bg-info me-2">1</span>{!! trans('backpack::tutorial.step_1') !!}
            </a>
            <div class="d-flex align-items-center">
              <button class="badge badge_button bg-info text-white p-2 me-3 border-0 complete_task" data-step="#step1" data-confirm-title="{!! trans('backpack::base.notice') !!}" data-confirm-message="{!! trans('backpack::tutorial.complete_task_message') !!}" data-success-title="{!! trans('backpack::tutorial.success_task_title') !!}" data-success-message="{!! trans('backpack::tutorial.success_task_message') !!}" data-complete-button="{!! trans('backpack::tutorial.complete_task') !!}" data-cancel-button="{!! trans('backpack::base.cancel') !!}" data-success-button="{!! trans('backpack::tutorial.completed_task') !!}" data-achievement="{!! trans('backpack::tutorial.achievement_1') !!}" data-achievement-gif="{!! trans('backpack::tutorial.achievement_1_gif') !!}">{!! trans('backpack::tutorial.complete_task') !!}</button>
              <small class="text-muted task-time">1 min</small>
            </div>
          </h5>
  
          </div>
          <div class="collapse" id="collapseStep1" role="tabpanel" aria-labelledby="headingStep1" data-parent="#accordionTutorial" style="">
            <div class="card-body">
              <p>{!! trans('backpack::tutorial.step_1_1') !!}
                <ul>
                  <li><strong class="text-primary">{{ trans('backpack::base.contents') }}</strong><code class="text-primary" style="background:none;">-></code>{!! trans('backpack::tutorial.step_1_2') !!}</li>
                  <li><strong class="text-primary">{{ trans('backpack::base.utilities') }}</strong><code class="text-primary" style="background:none;">-></code>{!! trans('backpack::tutorial.step_1_3') !!}</li>
                  <li><strong class="text-primary">{{ trans('backpack::base.administration') }}</strong><code class="text-primary" style="background:none;">-></code>{!! trans('backpack::tutorial.step_1_4') !!}</li>
                </ul>
                <p class="hint">{!! trans('backpack::tutorial.step_1_disclaimer') !!}</p>
              </p>
            </div>
          </div>
        </div>
  
        <div id="step2" class="card mb-1">
          <div class="card-header bg-light" id="headingStep2" role="tab">
            <h5 class="documentation_items mb-0 w-100 d-flex align-items-center justify-content-between">
              <a data-bs-toggle="collapse" href="#collapseStep2" aria-expanded="false" aria-controls="collapseStep2" class="collapsed">
                <span class="badge badge_button bg-info me-2">2</span>{!! trans('backpack::tutorial.step_2') !!}
              </a>
              <div class="d-flex align-items-center">
                <button class="badge badge_button bg-info text-white p-2 me-3 border-0 complete_task" data-step="#step2" data-confirm-title="{!! trans('backpack::base.notice') !!}" data-confirm-message="{!! trans('backpack::tutorial.complete_task_message') !!}" data-success-title="{!! trans('backpack::tutorial.success_task_title') !!}" data-success-message="{!! trans('backpack::tutorial.success_task_message') !!}" data-complete-button="{!! trans('backpack::tutorial.complete_task') !!}" data-cancel-button="{!! trans('backpack::base.cancel') !!}" data-success-button="{!! trans('backpack::tutorial.completed_task') !!}" data-achievement="{!! trans('backpack::tutorial.achievement_2') !!}" data-achievement-gif="{!! trans('backpack::tutorial.achievement_2_gif') !!}">{!! trans('backpack::tutorial.complete_task') !!}</button>
                <small class="text-muted task-time">1-3 min</small>
              </div>
            </h5>
          </div>
          <div class="collapse" id="collapseStep2" role="tabpanel" aria-labelledby="headingStep2" data-parent="#accordion" style="">
            <div class="card-body">
              <p>{!! trans('backpack::tutorial.step_2_1') !!}</p>
              <p class="m-0">{!! trans('backpack::tutorial.step_2_2') !!}</p>
              <p class="m-0">{!! trans('backpack::tutorial.step_2_3') !!}</p>
              <p>{!! trans('backpack::tutorial.step_2_4') !!}</p>
              <p>{!! trans('backpack::tutorial.step_2_5') !!}</p>
              <p class="mt-5"><span class="badge bg-warning text-uppercase">{!! trans('backpack::tutorial.dangerous_zone') !!}</span><p>
              <p class="m-0">{!! trans('backpack::tutorial.step_2_6') !!}</p>
              
            </div>
          </div>
        </div>
  
        <div id="step3" class="card mb-1">
          <div class="card-header bg-light" id="headingStep3" role="tab">
            <h5 class="documentation_items mb-0 w-100 d-flex align-items-center justify-content-between">
              <a data-bs-toggle="collapse" href="#collapseStep3" aria-expanded="false" aria-controls="collapseStep3" class="collapsed">
                <span class="badge badge_button bg-info me-2">3</span>{!! trans('backpack::tutorial.step_3') !!}
              </a>
              <div class="d-flex align-items-center">
                <button class="badge badge_button bg-info text-white p-2 me-3 border-0 complete_task" data-step="#step3" data-confirm-title="{!! trans('backpack::base.notice') !!}" data-confirm-message="{!! trans('backpack::tutorial.complete_task_message') !!}" data-success-title="{!! trans('backpack::tutorial.success_task_title') !!}" data-success-message="{!! trans('backpack::tutorial.success_task_message') !!}" data-complete-button="{!! trans('backpack::tutorial.complete_task') !!}" data-cancel-button="{!! trans('backpack::base.cancel') !!}" data-success-button="{!! trans('backpack::tutorial.completed_task') !!}" data-achievement="{!! trans('backpack::tutorial.achievement_3') !!}" data-achievement-gif="{!! trans('backpack::tutorial.achievement_3-5_gif') !!}">{!! trans('backpack::tutorial.complete_task') !!}</button>
                <small class="text-muted task-time">5-10 min</small>
              </div>
            </h5>
          </div>
          <div class="collapse" id="collapseStep3" role="tabpanel" aria-labelledby="headingStep3" data-parent="#accordion" style="">
            <div class="card-body">
              <p>{!! trans('backpack::tutorial.step_3_1') !!}</p>
              <ol>
                <li>{!! trans('backpack::tutorial.step_3_2') !!}</li>
                <li>{!! trans('backpack::tutorial.step_3_3') !!}</li>
                <li>{!! trans('backpack::tutorial.step_3_4') !!}</li>
                <li>{!! trans('backpack::tutorial.step_3_5') !!}</li>
                <p class="hint mb-3">{!! trans('backpack::tutorial.step_3_5_1') !!}</p>
                <li>{!! trans('backpack::tutorial.step_3_6') !!}</li>
              </ol>
              <p class="hint">{!! trans('backpack::tutorial.step_3_7') !!}</p>
            </div>
          </div>
        </div>
  
        <div id="step4" class="card mb-1">
          <div class="card-header bg-light" id="headingStep4" role="tab">
            <h5 class="documentation_items mb-0 w-100 d-flex align-items-center justify-content-between">
              <a data-bs-toggle="collapse" href="#collapseStep4" aria-expanded="false" aria-controls="collapseStep4" class="collapsed">
                <span class="badge badge_button bg-info me-2">4</span>{!! trans('backpack::tutorial.step_4') !!}
              </a>
              <div class="d-flex align-items-center">
                <button class="badge badge_button bg-info text-white p-2 me-3 border-0 complete_task" data-step="#step4" data-confirm-title="{!! trans('backpack::base.notice') !!}" data-confirm-message="{!! trans('backpack::tutorial.complete_task_message') !!}" data-success-title="{!! trans('backpack::tutorial.success_task_title') !!}" data-success-message="{!! trans('backpack::tutorial.success_task_message') !!}" data-complete-button="{!! trans('backpack::tutorial.complete_task') !!}" data-cancel-button="{!! trans('backpack::base.cancel') !!}" data-success-button="{!! trans('backpack::tutorial.completed_task') !!}" data-achievement="{!! trans('backpack::tutorial.achievement_4') !!}" data-achievement-gif="{!! trans('backpack::tutorial.achievement_3-5_gif') !!}">{!! trans('backpack::tutorial.complete_task') !!}</button>
                <small class="text-muted task-time">5-10 min</small>
              </div>
            </h5>
          </div>
          <div class="collapse" id="collapseStep4" role="tabpanel" aria-labelledby="headingStep4" data-parent="#accordion" style="">
            <div class="card-body">
              <p>{!! trans('backpack::tutorial.step_4_1') !!}</p>
            </div>
          </div>
        </div>
  
        <div id="step5" class="card mb-1">
          <div class="card-header bg-light" id="headingStep5" role="tab">
            <h5 class="documentation_items mb-0 w-100 d-flex align-items-center justify-content-between">
              <a data-bs-toggle="collapse" href="#collapseStep5" aria-expanded="false" aria-controls="collapseStep5" class="collapsed">
                <span class="badge badge_button bg-info me-2">5</span>{!! trans('backpack::tutorial.step_5') !!}
              </a>
              <div class="d-flex align-items-center">
                <button class="badge badge_button bg-info text-white p-2 me-3 border-0 complete_task" data-step="#step5" data-confirm-title="{!! trans('backpack::base.notice') !!}" data-confirm-message="{!! trans('backpack::tutorial.complete_task_message') !!}" data-success-title="{!! trans('backpack::tutorial.success_task_title') !!}" data-success-message="{!! trans('backpack::tutorial.success_task_message') !!}" data-complete-button="{!! trans('backpack::tutorial.complete_task') !!}" data-cancel-button="{!! trans('backpack::base.cancel') !!}" data-success-button="{!! trans('backpack::tutorial.completed_task') !!}" data-achievement="{!! trans('backpack::tutorial.achievement_5') !!}" data-achievement-gif="{!! trans('backpack::tutorial.achievement_3-5_gif') !!}">{!! trans('backpack::tutorial.complete_task') !!}</button>
                <small class="text-muted task-time">5-10 min</small>
              </div>
            </h5>
          </div>
          <div class="collapse" id="collapseStep5" role="tabpanel" aria-labelledby="headingStep5" data-parent="#accordion" style="">
            <div class="card-body">
              <p>{!! trans('backpack::tutorial.step_5_1') !!}</p>
            </div>
          </div>
        </div>
  
        <div id="step6" class="card mb-1">
          <div class="card-header bg-light" id="headingStep6" role="tab">
            <h5 class="documentation_items mb-0 w-100 d-flex align-items-center justify-content-between">
              <a data-bs-toggle="collapse" href="#collapseStep6" aria-expanded="false" aria-controls="collapseStep6" class="collapsed">
                <span class="badge badge_button bg-info me-2">6</span>{!! trans('backpack::tutorial.step_6') !!}
              </a>
              <div class="d-flex align-items-center">
                <button class="badge badge_button bg-info text-white p-2 me-3 border-0 complete_task" data-step="#step6" data-confirm-title="{!! trans('backpack::base.notice') !!}" data-confirm-message="{!! trans('backpack::tutorial.complete_task_message') !!}" data-success-title="{!! trans('backpack::tutorial.success_task_title') !!}" data-success-message="{!! trans('backpack::tutorial.success_task_message') !!}" data-complete-button="{!! trans('backpack::tutorial.complete_task') !!}" data-cancel-button="{!! trans('backpack::base.cancel') !!}" data-success-button="{!! trans('backpack::tutorial.completed_task') !!}" data-achievement="{!! trans('backpack::tutorial.achievement_6') !!}" data-achievement-gif="{!! trans('backpack::tutorial.achievement_6_gif') !!}">{!! trans('backpack::tutorial.complete_task') !!}</button>
                <small class="text-muted task-time">5-10 min</small>
              </div>
            </h5>
          </div>
          <div class="collapse" id="collapseStep6" role="tabpanel" aria-labelledby="headingStep6" data-parent="#accordion" style="">
            <div class="card-body">
              <p>{!! trans('backpack::tutorial.step_6_1') !!}</p>
            </div>
          </div>
        </div>
  
        <div id="step7" class="card mb-1">
          <div class="card-header bg-light" id="headingStep7" role="tab">
            <h5 class="documentation_items mb-0 w-100 d-flex align-items-center justify-content-between">
              <a data-bs-toggle="collapse" href="#collapseStep7" aria-expanded="false" aria-controls="collapseStep7" class="collapsed">
                <span class="badge badge_button bg-info me-2">7</span>{!! trans('backpack::tutorial.step_7') !!}
              </a>
              <div class="d-flex align-items-center">
                <button class="badge badge_button bg-info text-white p-2 me-3 border-0 complete_task" data-step="#step7" data-confirm-title="{!! trans('backpack::base.notice') !!}" data-confirm-message="{!! trans('backpack::tutorial.complete_task_message') !!}" data-success-title="{!! trans('backpack::tutorial.success_task_title') !!}" data-success-message="{!! trans('backpack::tutorial.success_task_message') !!}" data-complete-button="{!! trans('backpack::tutorial.complete_task') !!}" data-cancel-button="{!! trans('backpack::base.cancel') !!}" data-success-button="{!! trans('backpack::tutorial.completed_task') !!}" data-achievement="{!! trans('backpack::tutorial.achievement_7') !!}" data-achievement-gif="{!! trans('backpack::tutorial.achievement_7_gif') !!}">{!! trans('backpack::tutorial.complete_task') !!}</button>
                <small class="text-muted task-time">1-3 min</small>
              </div>
            </h5>
          </div>
          <div class="collapse" id="collapseStep7" role="tabpanel" aria-labelledby="headingStep7" data-parent="#accordion" style="">
            <div class="card-body">
              <p>{!! trans('backpack::tutorial.step_7_1') !!}</p>
              <ul>
                <li>{!! trans('backpack::tutorial.step_7_2') !!}</li>
                <li>{!! trans('backpack::tutorial.step_7_3') !!}</li>
                <p class="hint mb-3">{!! trans('backpack::tutorial.step_7_3_1') !!}</p>
                <li>{!! trans('backpack::tutorial.step_7_4') !!}</li>
                <li>{!! trans('backpack::tutorial.step_7_5') !!}</li>
              </ul>
            </div>
          </div>
        </div>
  
        
      </div>
    </div>
  </div>
  
  @push('after_styles')
    @basset('https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.6.0/styles/base16/dracula.min.css')
  @endpush
  
  @push('after_scripts')
    @basset('https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.6.0/highlight.min.js')
    <script>hljs.highlightAll();</script>
  @endpush
  
  @push('after_scripts')
    <script>
      hljs.highlightAll();
      if(localStorage.getItem('steps')){
        const steps = localStorage.getItem('steps').split(',');
        let successButton = "{!! trans('backpack::tutorial.completed_task') !!}";
        for (let i = 0; i < steps.length; i++) {
          $(steps[i]).addClass('completed_task');
          $(steps[i]).find(".complete_task").prop('disabled', true).addClass('pe-none', false).text(successButton);
        }
      }
    </script>
  @endpush
  
  