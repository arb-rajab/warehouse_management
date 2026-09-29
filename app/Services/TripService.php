<?php

namespace App\Services;

use Illuminate\Http\Request;
use setasign\Fpdi\Fpdi;
use App\Models\Checkpoint;
use App\Models\Upload;
use Auth;
use Image;
use setasign\Fpdi\TcpdfFpdi;  // Make sure you use the correct FPDI class

class  TripService
{

    public function uploadFiles($files)
    {
        $photos_ids = [];
        foreach ($files as $file) {

            $upload = new Upload;

            $upload->file_original_name = null;
            $arr = explode('.', $file->getClientOriginalName());
            for ($i = 0; $i < count($arr) - 1; $i++) {
                if ($i == 0) {
                    $upload->file_original_name .= $arr[$i];
                } else {
                    $upload->file_original_name .= "." . $arr[$i];
                }
            }

            $path = $file->store('uploads/all', 'local');
            $size = $file->getSize();

            try {
                $img = Image::make($file->getRealPath())->encode();
                $height = $img->height();
                $width = $img->width();
                if ($width > $height && $width > 1500) {
                    $img->resize(1500, null, function ($constraint) {
                        $constraint->aspectRatio();
                    });
                } elseif ($height > 1500) {
                    $img->resize(null, 800, function ($constraint) {
                        $constraint->aspectRatio();
                    });
                }
                $img->save(base_path('public/') . $path);
                clearstatcache();
                $size = $img->filesize();
            } catch (\Exception $e) {
                //dd($e);
            }

            $upload->file_name = $path;
            $upload->user_id = Auth::user()->id;
            $upload->file_size = $size;
            $upload->save();

            $photos_ids[] = $upload->id;
        }
        return $photos_ids;
    }

    public function process_cmr_file($id, Request $request)
    {
        $checkpoint = Checkpoint::find($id);

        if ($checkpoint) {

            if ($checkpoint->type == 'other') {
                $cmr_counter = 0;
            } else {
                $cmr_counter = get_setting('cmr_counter', 0);
            }

            $customer = $checkpoint->getCustomerForCheckpoint();

            // Define paths
            $templatePath = storage_path('app/PrintCMR-PDF-EN_2.pdf');
            $outputPath = storage_path('app/EDITED-PrintCMR-PDF-NL.pdf');

            // Initialize FPDI (Using TcpdfFpdi for template handling)
            $pdf = new TcpdfFpdi();  // Use TcpdfFpdi class here for template handling
            $pdf->AddPage();

            // Load the template
            $pdf->setSourceFile($templatePath);  // Now this method should work with FPDI
            $templateId = $pdf->importPage(1);
            $pdf->useTemplate($templateId);

            $this->cmrNumberSection($pdf, $cmr_counter);

            $this->sectionOne($pdf); // Company Data

            $this->sectionTwo($pdf, $customer); // Customer Company Address

            $this->sectionFour($pdf); // Company Location

            // $this->sectionFour($pdf, $request); // Company Shipping

            $this->sectionFive($pdf, $checkpoint); // Order Data

            $this->sectionSix($pdf); // Pallets

            $this->sectionThirteen($pdf, $checkpoint); // Notes

            if ($checkpoint->isOrder()) {
                $this->sectionSixteen($pdf, $checkpoint->getOrder()); // Representative
            }

            $this->sectionTwentyTwo($pdf); // Company Sign
            $this->sectionTwentyThree($pdf, $checkpoint); // Driver Sign
            $this->sectionTwentyFour($pdf, $customer, $request->cmr_file); // Customer Sign

            // Save the edited PDF
            $pdf->Output($outputPath, 'F');

            // Convert the saved file into a Symfony UploadedFile instance
            $file = new \Illuminate\Http\UploadedFile(
                $outputPath,
                basename($outputPath),
                'application/pdf',
                null,
                true
            );

            // Upload the file and get the IDs
            $files_ids = $this->uploadFiles([$file]);

            // Join the array values into a single string separated by commas
            $files_ids_string = implode(',', $files_ids);
            $checkpoint->cmr_file = $files_ids_string;

            $checkpoint->save();

            return response()->download($outputPath);
        } else {
            return response()->json(['error' => 'Checkpoint not found'], 404);
        }
    }


    private function sectionThirteen(&$pdf, $checkpoint)
    {
        $notes = $checkpoint->notes;

        $section_base_height = 195;

        $pdf->SetFont('dejavusans', '', 8, true);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetXY(20, $section_base_height);
        $pdf->Write(5, $notes);

        return $pdf;
    }

    private function sectionTwentyTwo(&$pdf)
    {
        $company_name = 'AL BARAKA BV';
        $company_address = 'SCHUTTEWAEREWEG 5';
        $company_some_text = '3047BA ROTTERDAM';

        $section_base_height = 260;

        $pdf->SetFont('dejavusans', 'B', 10, true);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetXY(20, $section_base_height);
        $pdf->Write(5, $company_name);

        $pdf->SetFont('dejavusans', 'B', 9, true);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetXY(20, $section_base_height + 5);
        $pdf->Write(5, $company_address);

        $pdf->SetFont('dejavusans', 'B', 9, true);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetXY(20, $section_base_height + 10);
        $pdf->Write(5, $company_some_text);

        return $pdf;
    }

    private function sectionTwentyThree(&$pdf, $checkpoint)
    {
        $driver_name = $checkpoint->trip->driver?->name;
        $truck_name = $checkpoint->trip->truck?->name;
        $arrived_at = $checkpoint->arrived_at;

        $section_base_height = 260;

        $pdf->SetFont('dejavusans', 'B', 10, true);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetXY(90, $section_base_height);
        $pdf->Write(5, $driver_name);

        $pdf->SetFont('dejavusans', 'B', 9, true);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetXY(80, $section_base_height + 5);
        $pdf->Write(5, $truck_name);

        $pdf->SetFont('dejavusans', '', 9, true);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetXY(80, $section_base_height + 8);
        $pdf->Write(5, $arrived_at);

        return $pdf;
    }

    private function sectionTwentyFour(&$pdf, $customer, $cmr_signature_image)
    {
        $customer_name = $customer->name;
        $address = $customer->addresses()->where('postal_code', '<>', null)->first();
        if ($address) {
            $address_text = $address?->address . ' ' . $address?->postal_code;
        } else {
            $address_text = $customer->company_address;
        }

        $section_base_height = 265;

        // Add the signature image
        if ($cmr_signature_image) {
            $cmrSignaturePath = $cmr_signature_image->getRealPath();

            // Detect image type if no extension exists
            $imageType = getimagesize($cmrSignaturePath)['mime'];
            $imageType = str_replace('image/', '', $imageType); // Convert 'image/png' to 'png', etc.

            $pdf->Image(
                $cmrSignaturePath,     // Path to the signature image
                140,                   // X position
                $section_base_height - 10, // Y position
                40,                    // Width
                10,                    // Height
                $imageType             // Explicitly specify the image type
            );
        }

        $pdf->SetFont('dejavusans', 'B', 10, true);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetXY(140, $section_base_height);
        $pdf->Write(5, $customer_name);

        $pdf->SetFont('dejavusans', 'B', 9, true);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetXY(130, $section_base_height + 5);
        $pdf->Write(5, $address_text);

        return $pdf;
    }

    private function sectionSixteen(&$pdf, $order)
    {
        if ($order->by_rep) {
            $rep = $order->rep;

            $rep_otajer_number = $rep->AccSysID;
            $rep_name = $rep->name;
            $order_number = $order->otajerOrderID;

            $section_base_height = 42;

            $pdf->SetFont('dejavusans', 'B', 14, true);
            $pdf->SetTextColor(0, 0, 0);
            $pdf->SetXY(140, $section_base_height);
            $pdf->Write(5, $rep_otajer_number);

            $pdf->SetXY(140, $section_base_height - 5);
            $barcode_width = 40;
            $barcode_height = 5;

            // Generate the barcode (you can adjust the type as needed, this example uses CODE128)
            $pdf->write1DBarcode((string)$rep_otajer_number, 'C128', '', '', $barcode_width, $barcode_height, 0.4, [], 'N');

            $pdf->SetFont('dejavusans', 'B', 10, true);
            $pdf->SetTextColor(0, 0, 0);
            $pdf->SetXY(140, $section_base_height + 5);
            $pdf->Write(5, $rep_name);

            // Adjust position for the address directly without excessive space
            $pdf->SetFont('dejavusans', '', 7, true);
            $pdf->SetXY(140, $section_base_height + 10);
            $pdf->Write(5, $order_number);

            $pdf->SetXY(140, $section_base_height + 5);
            $barcode_width = 40;
            $barcode_height = 5;

            // Generate the barcode (you can adjust the type as needed, this example uses CODE128)
            $pdf->write1DBarcode((string)$order_number, 'C128', '', '', $barcode_width, $barcode_height, 0.4, [], 'N');
        }
        return $pdf;
    }

    private function cmrNumberSection(&$pdf, $cmr_counter)
    {
        $section_base_height = 16;

        $pdf->SetFont('dejavusans', 'B', 10, true);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetXY(155, $section_base_height);
        $pdf->Write(5, $cmr_counter);

        $pdf->SetXY(160, $section_base_height);
        $barcode_width = 40;
        $barcode_height = 5;

        // Generate the barcode (you can adjust the type as needed, this example uses CODE128)
        $pdf->write1DBarcode((string)$cmr_counter, 'C128', '', '', $barcode_width, $barcode_height, 0.4, [], 'N');

        return $pdf;
    }

    private function sectionSix(&$pdf)
    {
        // Base height for section
        $section_base_height = 130;

        // Set font for the titles
        $pdf->SetFont('dejavusans', '', 8, true);
        $pdf->SetTextColor(0, 0, 0);

        // Draw the titles
        $pdf->SetXY(20, $section_base_height);
        $pdf->Write(5, 'LEVENSEMIDDELEN');

        $pdf->SetXY(70, $section_base_height);
        $pdf->Write(5, 'AANTAL PALETTEN');

        $pdf->SetXY(70, $section_base_height + 12);
        $pdf->Write(5, 'GEWICHT');

        // Draw boxes
        $pdf->SetLineWidth(0.1); // Set box line thickness
        $pdf->Rect(110, $section_base_height, 30, 5); // Box for AANTAL PALETTEN
        $pdf->Rect(110, $section_base_height + 5, 30, 5); // Box for GEWICHT

        $pdf->Rect(110, $section_base_height + 12, 30, 5); // Box for AANTAL PALETTEN
        $pdf->Rect(110, $section_base_height + 17, 30, 5); // Box for GEWICHT

        // Add notes or footnotes
        $pdf->SetFont('dejavusans', '', 8, true);
        $pdf->SetXY(20, $section_base_height + 35);
        $pdf->Write(5, 'After signing CMR you have agreed to our terms and conditions and you confirm that goods are delivered.');

        return $pdf;
    }

    private function sectionFive(&$pdf, $checkpoint)
    {
        if ($checkpoint->isOrder()) {
            $order = $checkpoint->getOrder();
            $order_code = $order->code ?? '24-2528415';
            $order_date = $order->delivery_date ?? '17/12/2024';
        } else {
            $order_code = '24-2528415';
            $order_date = '17/12/2024';
        }

        $section_base_height = 102;

        $pdf->SetFont('dejavusans', 'B', 10, true);
        $pdf->SetTextColor(0, 0, 0);

        // Write Order Code in first line
        $pdf->SetXY(20, $section_base_height);
        $pdf->Write(5, "Order Code: $order_code");

        // Write Delivery Date in next line
        $pdf->SetXY(20, $section_base_height + 6); // Move down by 6 units
        $pdf->Write(5, "Delivery Date: $order_date");

        return $pdf;
    }



    private function sectionThree(&$pdf)
    {
        $company_address = 'Sydneystraat 10, 3047 BP Rotterdam';
        $company_phone = 'KvK nr.:76275140      BTW nr.:NL860571245B01';

        $section_base_height = 66;

        $pdf->SetFont('dejavusans', 'B', 10, true);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetXY(20, $section_base_height);
        $pdf->Write(5, $company_address);

        // Adjust position for the address directly without excessive space
        $pdf->SetFont('dejavusans', 'B', 10, true);
        $pdf->SetXY(20, $section_base_height + 5);
        $pdf->Write(5, $company_phone);

        return $pdf;
    }

    private function sectionFour(&$pdf)
    {
        $company_address = 'Sydneystraat 10, 3047 BP Rotterdam';
        $company_phone = 'KvK nr.:76275140      BTW nr.:NL860571245B01';

        $section_base_height = 80;

        $pdf->SetFont('dejavusans', 'B', 10, true);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetXY(20, $section_base_height);
        $pdf->Write(5, $company_address);

        $pdf->SetFont('dejavusans', 'B', 10, true);
        $pdf->SetXY(20, $section_base_height + 5);
        $pdf->Write(5, $company_phone);

        return $pdf;
    }


    private function sectionTwo(&$pdf, $customer)
    {
        $company_name = $customer?->company_name ?? '-';
        $tax_number = $customer?->tax_number ?? '-';
        $address = $customer?->address ?? '-';
        $phone = $customer?->phone ?? '-';
        $email = $customer?->email ?? '-';

        $y = 41;

        $pdf->SetFont('dejavusans', 'B', 10);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetXY(20, $y);
        $pdf->Write(5, $company_name);

        $pdf->SetFont('dejavusans', '', 8);
        $y += 5;
        $pdf->SetXY(20, $y);
        $pdf->Write(5, 'Tax Number: ' . $tax_number);

        $y += 5;
        $pdf->SetXY(20, $y);
        $pdf->Write(5, 'Address: ' . $address);


        $y += 5;
        $pdf->SetXY(20, $y);
        $pdf->Write(5, 'Phone: ' . $phone);

        $y += 5;
        $pdf->SetXY(20, $y);
        $pdf->Write(5, 'Email: ' . $email);

        return $pdf;
    }


    private function sectionOne(&$pdf)
    {
        $company_name = 'AL BARAKA BV';
        $company_address = 'Sydneystraat 10, 3047 BP Rotterdam';
        $company_phone = 'KvK nr.:76275140      BTW nr.:NL860571245B01';

        $section_base_height = 17;

        $pdf->SetFont('dejavusans', 'B', 10, true);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetXY(20, $section_base_height);
        $pdf->Write(5, $company_name);

        // Adjust position for the address directly without excessive space
        $pdf->SetFont('dejavusans', '', 7, true);
        $pdf->SetXY(20, $section_base_height + 5);
        $pdf->Write(5, $company_address);

        // Adjust position for the phone number directly
        $pdf->SetXY(20, $section_base_height + 10);
        $pdf->Write(5, $company_phone);

        return $pdf;
    }
}
