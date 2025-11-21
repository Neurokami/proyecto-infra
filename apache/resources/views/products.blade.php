@extends("layouts/app")

@section("content")

    <div style="width: 70%; margin: 2em auto;">
        <form action="" class="searchbar">
            <input type="search" name="s" class="input" style="flex: 1 1 100%" placeholder="Busca por nombre del producto" value="{{ request('s') }}">
            <button class="btn btn-primary">Buscar</button>
        </form>    
    </div>

    <div class="products-wrapper" style="width: 70%; margin: 2em auto;">
        @forelse ($products as $p)
            <x-product-card :p="$p" />
        @empty 
            <div>
                No hay productos que ver aqui!
            </div>
        @endforelse
    </div>
    

@endsection