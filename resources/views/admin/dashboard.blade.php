@extends('admin.layouts.app')
@section('content')
    <div class="container mt-4">

        {{-- Pending Withdrawals Alert --}}
{{--        @if($dashboardData['pendingWithdrawals'] > 0)--}}
{{--            <a href="/withdraw" class="text-decoration-none">--}}
{{--                <div class="alert alert-warning d-flex align-items-center shadow-sm rounded p-3 mb-4">--}}
{{--                    <i class="fas fa-exclamation-triangle text-dark fs-4 me-3"></i>--}}
{{--                    <div class="fw-semibold text-dark">--}}
{{--                        You currently have {{ $dashboardData['pendingWithdrawals'] }} pending withdrawal {{ $dashboardData['pendingWithdrawals'] > 1 ? 'requests' : 'request' }}.--}}
{{--                    </div>--}}
{{--                </div>--}}
{{--            </a>--}}
{{--        @endif--}}

        {{-- Section --}}
        <div class="card shadow-sm mb-4 border-0">
            <div class="card-body">
                <h5 class="card-title fw-bold mb-4">Overview</h5>
                <div class="row g-4">

                    @php
                        $users = [
                             ['label' => 'Total USDT', 'value' => $dashboardData['totalUsddtTrx'], 'icon' => 'fas fa-dollar-sign', 'bg' => 'bg-success'],
                             ['label' => 'Today Transaction', 'value' => $dashboardData['today_trx'], 'icon' => 'fas fa-user', 'bg' => 'bg-success'],
                            ['label' => 'Total Transaction', 'value' => $dashboardData['totalTrx'], 'icon' => 'fas fa-user', 'bg' => 'bg-success'],
                             ['label' => 'Total Customers', 'value' => $dashboardData['totalCustomer'], 'icon' => 'fas fa-user', 'bg' => 'bg-success'],

                             ['label' => 'Total Coin', 'value' => $dashboardData['totalCoin'], 'icon' => 'fas fa-user', 'bg' => 'bg-success'],
                             ['label' => 'Total Network', 'value' => $dashboardData['totalChain'], 'icon' => 'fas fa-user', 'bg' => 'bg-success'],


                             ['label' => 'Total License', 'value' => $dashboardData['totalLicense'], 'icon' => 'fas fa-user', 'bg' => 'bg-success'],
                             ['label' => 'Expire License', 'value' => $dashboardData['expireLicense'], 'icon' => 'fas fa-user', 'bg' => 'bg-success'],
                        ];
                    @endphp

                    @foreach ($users as $user)
                        <div class="col-md-3">
                            <div class="d-flex justify-content-between align-items-center border rounded p-3 h-100 bg-light hover-shadow">
                                <div class="d-flex align-items-center">
                                    <div class="icon-box {{ $user['bg'] }} bg-opacity-75 text-white rounded d-flex justify-content-center align-items-center me-3" style="width: 48px; height: 48px;">
                                        <i class="{{ $user['icon'] }}"></i>
                                    </div>
                                    <div>
                                        <div class="fw-bold fs-5">{{ $user['value'] }}</div>
                                        <small class="text-muted">{{ $user['label'] }}</small>
                                    </div>
                                </div>
                              <a href="/users"> <i class="fas fa-arrow-right text-muted"></i></a>
                            </div>
                        </div>
                    @endforeach


                </div>
            </div>
        </div>

    </div>
@endsection
