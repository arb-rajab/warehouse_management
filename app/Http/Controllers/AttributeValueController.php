<?php

namespace App\Http\Controllers;

use App\Http\Requests\AttributeValueRequest;
use App\Models\AttributeValue;
use Illuminate\Http\Response;

class AttributeValueController extends Controller
{
    /**
     * Update the specified resource in storage.
     *
     * @param  Request  $request
     * @param  int  $id
     * @return Response
     */
    public function update(AttributeValueRequest $request, AttributeValue $attribute_value)
    {
        $attribute_value->update($request->validated());

        flash(translate('Attribute value has been updated successfully'))->success();
        return back();
    }
}
