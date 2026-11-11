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
            {{-- a text box, marked as no password for the usual password managers: a password box gets the site's login
                 filled into it --}}
            <input id="clickbank_secret" type="text" class="form-control input-sized" name="settings[{{ $secrets['clickbank'] }}]" value="{{ old('settings.'.$secrets['clickbank'], $settings[$secrets['clickbank']]) }}" maxlength="255" autocomplete="off" spellcheck="false" data-lpignore="true" data-1p-ignore data-bwignore="true" data-form-type="other">
            <p class="form-help">{{ __('Up to 16 capital letters and digits.') }} {{ __('A saved key shows as stars; to change it, clear the field first.') }}</p>
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
            {{-- a text box, marked as no password for the usual password managers: a password box gets the site's login
                 filled into it --}}
            <input id="jvzoo_secret" type="text" class="form-control input-sized" name="settings[{{ $secrets['jvzoo'] }}]" value="{{ old('settings.'.$secrets['jvzoo'], $settings[$secrets['jvzoo']]) }}" maxlength="255" autocomplete="off" spellcheck="false" data-lpignore="true" data-1p-ignore data-bwignore="true" data-form-type="other">
            <p class="form-help">{{ __('A saved key shows as stars; to change it, clear the field first.') }}</p>
            @include('partials/field_error', ['field' => 'settings.'.$secrets['jvzoo']])
        </div>
    </div>

    <div class="form-group margin-top">
        <div class="col-sm-6 col-sm-offset-2">
            <button type="submit" class="btn btn-primary">{{ __('Save') }}</button>
        </div>
    </div>
</form>
