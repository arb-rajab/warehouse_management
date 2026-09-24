@extends('backend.layouts.app')

@section('content')
    @php
        use Carbon\Carbon;

    @endphp
    <div class="aiz-titlebar text-left mt-2 mb-3">
        <div class="align-items-center">
            <h1 class="h3">{{ translate('All Trucks') }}</h1>
        </div>
    </div>


    <div class="card">
        <form class="" id="sort_trucks" action="" method="GET">
            <div class="card-header row gutters-5">
                <div class="col">
                    <h5 class="mb-0 h6">{{ translate('Trucks') }}</h5>
                </div>

                <div class="col-md-3">
                    <div class="form-group mb-0">
                        <input type="text" class="form-control" id="search"
                            name="search"@isset($sort_search) value="{{ $sort_search }}" @endisset
                            placeholder="{{ translate('Type License Plate or name & Enter') }}">
                    </div>
                </div>
            </div>

            <div class="card-body">
                <table class="table aiz-table mb-0">
                    <thead>
                        <tr>
                            <!--<th data-breakpoints="lg">#</th>-->
                            <th>{{ translate('Name') }}</th>
                            <th>{{ translate('ID') }}</th>
                            <th>{{ translate('Model') }}</th>
                            <th>{{ translate('Max Pallets Number') }}</th>
                            <th>{{ translate('Description') }}</th>
                            <th>{{ translate('License Plate') }}</th>
                            {{-- <th>{{ translate('Is Available') }}</th> --}}
                            <th>{{ translate('Options') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($trucks as $key => $truck)
                            <tr>
                                <td>
                                    <div class="row gutters-5 w-200px w-md-300px mw-100">
                                        <div class="col-auto">
                                            <img src="{{ get_images_path($truck->photos)[0] ?? '' }}" alt="Image"
                                                class="size-50px img-fit rounded-circle">
                                        </div>
                                        <div class="col">
                                            <span class="text-muted text-truncate-2">
                                                <a href="{{ $truck->lastTrip() ? route('trips.show', $truck->lastTrip()->id) : '#' }}">
                                                    {{ $truck->name }}
                                                </a>
                                            </span>
                                        </div>
                                    </div>
                                </td>
                                <td>{{ $truck->id }}</td>
                                <td>{{ $truck->model }}</td>
                                <td>{{ $truck->max_pallets_number }}</td>
                                <td>{{ $truck->description }}</td>
                                <td>
                                    <span class="badge badge-inline badge-light">
                                        {{ $truck->license_plate }}
                                    </span>
                                </td>
                                {{-- <td>{{ $truck->is_available }}</td> --}}


                                <td>
                                    <div class="dropdown mb-2 mb-md-0 ">
                                        <button class="btn btn-sm border dropdown-toggle" type="button"
                                            data-toggle="dropdown">
                                            {{ translate('Actions') }}
                                        </button>

                                        <div class="dropdown-menu dropdown-menu-right">

                                            <a href="{{route('trucks.edit', $truck->id)}}" class="dropdown-item" data-href="{{route('trucks.edit', $truck->id)}}" title="{{ translate('Edit') }}">
                                                {{ translate('Edit') }}
                                            </a>

                                            <a href="#" class="dropdown-item" title="{{ translate('Print QR') }}"
                                                onclick="printQR('{{ $truck->license_plate }}')">
                                                    {{ translate('Print QR') }}
                                            </a>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                <div class="aiz-pagination">
                    {{ $trucks->appends(request()->input())->links() }}
                </div>
            </div>
        </form>
    </div>


@endsection

@section('modal')
@endsection

@section('script')
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcode/1.5.0/qrcode.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.4.0/jspdf.umd.min.js"></script>

    <script type="text/javascript">
        function printQR(truckCode) {
            // Ensure truckCode is a string
            const truckCodeString = truckCode.toString();
            // Generate QR code
            const qrCanvas = document.createElement('canvas');
            QRCode.toCanvas(qrCanvas, truckCodeString, { width: 400 }, function (error) {
                if (error) {
                    console.error("Error generating QR Code: ", error);
                    alert("Failed to generate QR code");
                    return;
                }

                // Convert the canvas to an image
                const qrImageData = qrCanvas.toDataURL("image/png");

                // Create PDF with jsPDF
                const { jsPDF } = window.jspdf;
                const pdf = new jsPDF();

                // Get the page dimensions
                const pageWidth = pdf.internal.pageSize.getWidth();
                const pageHeight = pdf.internal.pageSize.getHeight();

                // Dimensions for the QR code
                const qrSize = 200; // Increase size as needed (e.g., 100mm x 100mm)

                // Calculate center position
                const qrX = (pageWidth - qrSize) / 2;
                const qrY = (pageHeight - qrSize) / 2;

                // Add text above the QR code
                pdf.setFontSize(26);
                pdf.text(`QR Code for Truck: ${truckCodeString}`, pageWidth / 2, qrY - 10, { align: "center" });

                // Add the QR code image to the PDF
                pdf.addImage(qrImageData, "PNG", qrX, qrY, qrSize, qrSize);

                // Open the print dialog
                const pdfBlob = pdf.output("blob"); // Generate a Blob of the PDF
                const pdfUrl = URL.createObjectURL(pdfBlob);
                const iframe = document.createElement("iframe");
                iframe.style.display = "none";
                iframe.src = pdfUrl;
                iframe.onload = function () {
                    iframe.contentWindow.print(); // Trigger the print dialog
                };
                document.body.appendChild(iframe);
            });
        }

    </script>
    <script type="text/javascript">
        function sort_trucks(el) {
            $('#sort_trucks').submit();
        }
    </script>
@endsection
