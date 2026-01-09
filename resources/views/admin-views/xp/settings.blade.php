@extends('layouts.admin.app')

@section('title', translate('messages.xp_settings'))

@section('content')
<div class="content container-fluid">
    <!-- Page Header -->
    <div class="page-header">
        <h1 class="page-header-title mr-3">
            <span class="page-header-icon">
                <i class="tio-settings text-primary"></i>
            </span>
            <span>{{translate('messages.xp_settings')}}</span>
        </h1>
    </div>

    <!-- Content -->
    <div class="card">
        <div class="card-body">
            <form action="{{route('admin.users.customer.xp.settings.update')}}" method="POST">
                @csrf
                
                <!-- System Status -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5>{{translate('messages.system_status')}}</h5>
                    </div>
                    <div class="card-body">
                        <div class="form-group">
                            <div class="d-flex align-items-center">
                                <label class="toggle-switch mr-3">
                                    <input type="checkbox" name="leveling_status" value="1" {{($settings['leveling_status'] ?? '1') == '1' ? 'checked' : ''}}>
                                    <span class="toggle-switch-slider"></span>
                                </label>
                                <span>{{translate('messages.enable_xp_leveling_system')}}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- XP Values -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5>{{translate('messages.xp_values')}}</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="input-label">{{translate('messages.xp_per_order_completion')}}</label>
                                    <input type="number" name="xp_per_order" class="form-control" value="{{$settings['xp_per_order'] ?? 20}}" min="0">
                                    <small class="text-muted">{{translate('messages.xp_awarded_when_order_is_delivered')}}</small>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="input-label">{{translate('messages.xp_per_review')}}</label>
                                    <input type="number" name="xp_per_review" class="form-control" value="{{$settings['xp_per_review'] ?? 30}}" min="0">
                                    <small class="text-muted">{{translate('messages.xp_awarded_for_rating_and_review')}}</small>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="input-label">{{translate('messages.xp_daily_challenge')}}</label>
                                    <input type="number" name="xp_daily_challenge" class="form-control" value="{{$settings['xp_daily_challenge'] ?? 20}}" min="0">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="input-label">{{translate('messages.xp_weekly_challenge')}}</label>
                                    <input type="number" name="xp_weekly_challenge" class="form-control" value="{{$settings['xp_weekly_challenge'] ?? 100}}" min="0">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="input-label">{{translate('messages.xp_signup_bonus')}}</label>
                                    <input type="number" name="xp_signup_bonus" class="form-control" value="{{$settings['xp_signup_bonus'] ?? 50}}" min="0">
                                    <small class="text-muted">{{translate('messages.xp_awarded_for_new_user_registration')}}</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Vertical Multipliers -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5>{{translate('messages.vertical_multipliers')}}</h5>
                        <small class="text-muted">{{translate('messages.xp_from_amount_spent_is_multiplied_by_these_values')}}</small>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label class="input-label">{{translate('messages.food')}}</label>
                                    <input type="number" step="0.01" name="multiplier_food" class="form-control" value="{{$settings['multiplier_food'] ?? 1.0}}" min="0" max="2">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label class="input-label">{{translate('messages.pharmacy')}}</label>
                                    <input type="number" step="0.01" name="multiplier_pharmacy" class="form-control" value="{{$settings['multiplier_pharmacy'] ?? 0.5}}" min="0" max="2">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label class="input-label">{{translate('messages.grocery')}}</label>
                                    <input type="number" step="0.01" name="multiplier_grocery" class="form-control" value="{{$settings['multiplier_grocery'] ?? 0.25}}" min="0" max="2">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label class="input-label">{{translate('messages.parcel')}}</label>
                                    <input type="number" step="0.01" name="multiplier_parcel" class="form-control" value="{{$settings['multiplier_parcel'] ?? 0.1}}" min="0" max="2">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Prize Settings -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5>{{translate('messages.prize_settings')}}</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="input-label">{{translate('messages.prize_validity_days')}}</label>
                                    <input type="number" name="prize_validity_days" class="form-control" value="{{$settings['prize_validity_days'] ?? 30}}" min="1">
                                    <small class="text-muted">{{translate('messages.number_of_days_before_prize_expires')}}</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="btn--container justify-content-end">
                    <button type="reset" class="btn btn--reset">{{translate('messages.reset')}}</button>
                    <button type="submit" class="btn btn--primary">{{translate('messages.save')}}</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
