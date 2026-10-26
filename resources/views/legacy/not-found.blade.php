{{--
    sh404SEF's 404 page as the live site serves it for a path the registry does
    not know: the "404" article (jos_content id fec.legacy.not_found_article_id)
    rendered as a com_content item page inside the .fecx shell, status 404,
    robots noindex,follow, canonical on the requested URL.
--}}
@extends('layouts.fecx')

@section('title', $article?->title ?? '404')
@section('robots', 'noindex, follow')
@section('canonical', $canonical)

@section('content')
        <div class="item-page" itemscope itemtype="https://schema.org/Article">




    <div itemprop="articleBody">
{!! $article ? $article->introtext.$article->fulltext : '<h1 class="nf-h">Oups! Something went wrong here...</h1>' !!}
    </div>


</div>
@endsection
