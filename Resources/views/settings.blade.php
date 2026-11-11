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

    @php($member = \Modules\ClickBankJVZoo\Providers\ClickBankJVZooServiceProvider::MEMBER)
    <h3 class="subheader">{{ __('Member site') }}</h3>
    <div class="descr-block">
        <p>{{ __('Each order gets a code. A member site lets the buyer use it there, asking this help desk first in one signed call. Give the member site these two:') }}</p>
        <p>{{ __('The address it calls:') }} <code>{{ $redeem_url }}</code></p>
        <p>{{ __("This help desk's public key:") }} <code>{{ $own_public }}</code></p>
    </div>

    <div class="form-group{{ $errors->has('settings.'.$member['public']) ? ' has-error' : '' }}">
        <label for="member_public" class="col-sm-2 control-label">{{ __("Member site's public key") }}</label>
        <div class="col-sm-6">
            <input id="member_public" type="text" class="form-control input-sized" name="settings[{{ $member['public'] }}]" value="{{ old('settings.'.$member['public'], $settings[$member['public']]) }}" maxlength="255" autocomplete="off" spellcheck="false" data-lpignore="true" data-1p-ignore data-bwignore="true" data-form-type="other">
            <p class="form-help">{{ __('From the member site: its Ed25519 public key, in base64.') }}</p>
            @include('partials/field_error', ['field' => 'settings.'.$member['public']])
        </div>
    </div>

    <div class="form-group{{ $errors->has('settings.'.$member['url']) ? ' has-error' : '' }}">
        <label for="member_url" class="col-sm-2 control-label">{{ __("Member site's address for refunds") }}</label>
        <div class="col-sm-6">
            <input id="member_url" type="text" class="form-control input-sized" name="settings[{{ $member['url'] }}]" value="{{ old('settings.'.$member['url'], $settings[$member['url']]) }}" maxlength="255" autocomplete="off" spellcheck="false" data-lpignore="true" data-1p-ignore data-bwignore="true" data-form-type="other">
            <p class="form-help">{{ __("Told when a used code's order is refunded, charged back or canceled.") }}</p>
            @include('partials/field_error', ['field' => 'settings.'.$member['url']])
        </div>
    </div>

    <div class="form-group margin-top">
        <div class="col-sm-6 col-sm-offset-2">
            <button type="submit" class="btn btn-primary">{{ __('Save') }}</button>
        </div>
    </div>
</form>
