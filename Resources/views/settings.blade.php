@php($secrets = \Modules\ClickBankJVZoo\Providers\ClickBankJVZooServiceProvider::SECRETS)
<form class="form-horizontal margin-top" method="POST" action="">
    {{ csrf_field() }}

    <h3 class="subheader">ClickBank</h3>
    <div class="descr-block">
        <p>{{ __('In ClickBank: Vendor Settings » My Site » Advanced Tools. Set the same Secret Key there and here, then give ClickBank this address as the Instant Notification URL, version 8.0:') }}</p>
        <p><code>{{ $notice_urls['clickbank'] }}</code></p>
    </div>

    <div class="form-group{{ $errors->has('settings.'.$secrets['clickbank']) ? ' has-error' : '' }}">
        <label for="clickbank_secret" class="col-sm-2 control-label">{{ __('Secret Key') }}</label>
        <div class="col-sm-6">
            <input id="clickbank_secret" type="password" class="form-control input-sized" name="settings[{{ $secrets['clickbank'] }}]" value="{{ old('settings.'.$secrets['clickbank'], \Helper::safePassword($settings[$secrets['clickbank']])) }}" maxlength="255" autocomplete="new-password">
            <p class="form-help">{{ __('Up to 16 capital letters and digits.') }}</p>
            @include('partials/field_error', ['field' => 'settings.'.$secrets['clickbank']])
        </div>
    </div>

    <h3 class="subheader">JVZoo</h3>
    <div class="descr-block">
        <p>{{ __("In JVZoo: set the same Secret Key there and here, then give JVZoo this address as your product's JVZIPN URL:") }}</p>
        <p><code>{{ $notice_urls['jvzoo'] }}</code></p>
    </div>

    <div class="form-group{{ $errors->has('settings.'.$secrets['jvzoo']) ? ' has-error' : '' }}">
        <label for="jvzoo_secret" class="col-sm-2 control-label">{{ __('Secret Key') }}</label>
        <div class="col-sm-6">
            <input id="jvzoo_secret" type="password" class="form-control input-sized" name="settings[{{ $secrets['jvzoo'] }}]" value="{{ old('settings.'.$secrets['jvzoo'], \Helper::safePassword($settings[$secrets['jvzoo']])) }}" maxlength="255" autocomplete="new-password">
            @include('partials/field_error', ['field' => 'settings.'.$secrets['jvzoo']])
        </div>
    </div>

    <div class="form-group margin-top">
        <div class="col-sm-6 col-sm-offset-2">
            <button type="submit" class="btn btn-primary">{{ __('Save') }}</button>
        </div>
    </div>
</form>
