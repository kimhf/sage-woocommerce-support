@extends(\Kimhf\SageWoocommerceSupport\FallbackTemplate::getBladeLayout())

@section('content')
	@php(woocommerce_content())
@endsection
