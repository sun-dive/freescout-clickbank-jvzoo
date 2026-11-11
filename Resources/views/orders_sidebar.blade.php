{{-- Beside a ticket, above Previous Conversations: the customer's orders, read-only, in the style of that block. --}}
<div class="conv-sidebar-block">
    <div class="panel-group accordion accordion-empty">
        <div class="panel panel-default">
            <div class="panel-heading">
                <h4 class="panel-title">
                    <a data-toggle="collapse" href=".collapse-clickbankjvzoo-orders">{{ __('Orders') }} ({{ count($orders) }})
                        <b class="caret"></b>
                    </a>
                </h4>
            </div>
            <div class="collapse-clickbankjvzoo-orders panel-collapse collapse in">
                <div class="panel-body">
                    <ul class="sidebar-block-list">
                        @foreach ($orders as $order)
                            <li class="clickbankjvzoo-order">
                                <strong>{{ $order->title !== '' ? $order->title : $order->item }}</strong><br>
                                <span class="label label-{{ \Modules\ClickBankJVZoo\Entities\Order::STATUS_CLASS[$order->status] ?? 'default' }}">{{ __($order->status) }}</span>
                                @if ($order->accessed_at)
                                    <span class="label label-info" title="{{ $order->accesses }}×">{{ __('accessed') }} {{ \App\User::dateFormat($order->accessed_at, 'M j') }}</span>
                                @else
                                    <span class="label label-default">{{ __('not accessed') }}</span>
                                @endif
                                @if ($order->test)
                                    <span class="label label-warning">{{ __('test') }}</span>
                                @endif
                                <br>
                                <small class="text-help">
                                    {{ \Modules\ClickBankJVZoo\Entities\Order::SOURCES[$order->source] ?? $order->source }}
                                    @if ($order->price !== null) · {{ number_format((float)$order->price, 2) }}{{ $order->currency !== '' ? ' '.$order->currency : '' }}@endif
                                    @if ($order->bought_at) · {{ \App\User::dateFormat($order->bought_at, 'M j, Y') }}@endif
                                    · {{ __('receipt') }} {{ $order->receipt }}
                                </small>
                            </li>
                        @endforeach
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>
