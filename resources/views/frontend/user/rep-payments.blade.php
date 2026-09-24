@extends('frontend.layouts.user_panel')

@section('panel_content')
    <div class="card shadow-none rounded-0 border">
        <div class="card-header border-bottom-0 d-flex justify-content-between align-items-center">
            <h5 class="mb-0 fs-20 fw-700 text-dark">{{ translate('Representative Payments') }}</h5>
            <a href="{{ route('payments.create') }}" class="btn btn-sm btn-primary">{{ translate('Add New Payment') }}</a>
        </div>
        <div class="card-body">
            <table class="table aiz-table mb-0">
                <thead class="text-gray fs-12">
                    <tr>
                        <th>{{ translate('Receipt Code') }}</th>
                        <th>{{ translate('Customer Name') }}</th>
                        <th>{{ translate('Customer Account ID') }}</th>
                        <th>{{ translate('Invoice Code') }}</th>
                        <th>{{ translate('Accountant') }}</th>
                        <th>{{ translate('Amount') }}</th>
                        <th>{{ translate('Date') }}</th>
                        <th>{{ translate('Notes') }}</th>
                        <th>{{ translate('Status') }}</th>
                        <th class="text-right pr-0">{{ translate('Options')}}</th>
                    </tr>
                </thead>
                <tbody class="fs-14">
                    @foreach ($payments as $key => $payment)
                        <tr>
                            <td>
                                <a href="{{route('payments.show', $payment->id)}}">
                                    {{ $payment->receipt_code }}
                                </a>
                            </td>
                            <td>{{ $payment->customer?->name }}</td>
                            <td>{{ $payment->customer?->AccSysID }}</td>
                            <td>{{ $payment->invoice_code }}</td>
                            <td>{{ $payment->admin?->name }}</td>
                            <td>{{ $payment->amount }}</td>
                            <td>{{ \carbon\Carbon::parse($payment->date)->format('Y-m-d') }}</td>
                            <td>{{ $payment->notes }}</td>
                            <td class="text-center">
                                @if ($payment->status == 'pending')
                                    <div class="badge badge-warning">
                                        <i class="las la-clock"></i>
                                    </div>
                                @elseif ($payment->status == 'accepted')
                                    <div class="badge badge-success">
                                        <i class="las la-check"></i>
                                    </div>
                                @else
                                    <div class="badge badge-danger">
                                        <i class="las la-times"></i>
                                    </div>
                                @endif
                            </td>
                            <td class="text-right pr-0">
                                <a href="{{route('payments.share-by-whatsapp', $payment->id)}}"
                                    class="btn btn-soft-success btn-icon btn-circle btn-sm hov-svg-white"
                                    title="{{ translate('Send PDF to Customer Whatsapp') }}">
                                    <i class="lab la-whatsapp"></i>
                                </a>

                                <a href="{{route('payments.export', $payment->id)}}"
                                    class="btn btn-soft-info btn-icon btn-circle btn-sm hov-svg-white"
                                    title="{{ translate('Export Payment Details') }}">
                                    <i class="las la-download"></i>
                                </a>

                                <button class="btn btn-soft-warning btn-icon btn-circle btn-sm hov-svg-white" onclick="generateQR({{ $payment->id }}, this)" title="{{ translate('Generate QR') }}">
                                    <i class="las la-qrcode"></i>
                                </button>

                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            <!-- Pagination -->
            <div class="aiz-pagination">
                {{ $payments->appends(request()->input())->links() }}
            </div>
        </div>
    </div>
@endsection

@section('modal')
    <!-- Delete modal -->
    @include('modals.delete_modal')

    <!-- qr Modal -->
    <div id="qr-modal" class="modal fade">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h4 class="modal-title h6">{{translate('QR Code')}}</h4>
                    <button type="button" class="close" data-dismiss="modal" aria-hidden="true"></button>
                </div>
                <div class="modal-body text-center" id="qr-modal-body">
                    <!-- Response HTML will be displayed here -->
                </div>
            </div>
        </div>
    </div>
    <!-- /.modal -->
@endsection

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
    function generateQR(id, element) {

        $.ajax({
            url: '{{ route('payments.generate_qr') }}' ,
            data: { id: id },
            method: 'get',
            success: function(response) {
                $('#qr-modal').modal('show');

                $('#qr-modal-body').html(response);
            },
            error: function(xhr, status, error) {
                console.error('Error:', error);
            }
        });
    };
</script>
