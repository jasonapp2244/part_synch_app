<h2>Hello {{ $user->first_name }},</h2>

<p>Good news! The product you added to your wishlist is now back in stock:</p>

<h3>{{ $product->name }}</h3>
<p>{{ $product->description }}</p>

{{-- <a href="{{ url('/product/'.$product->id) }}">View Product</a> --}}

<p>Hurry! Limited stock available.</p>
<br>
<br>
<h3>Best Regards</h3>
<p>Part Synch</p>
