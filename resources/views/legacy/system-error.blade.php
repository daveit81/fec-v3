{{-- Joomla 2.5's templates/system/error.php, as the live site shows it when a component raises an error with an HTTP code. --}}
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en-gb" lang="en-gb" dir="ltr">
<head>
	<meta http-equiv="content-type" content="text/html; charset=utf-8" />
	<title>{{ $code }} - {{ $message }}</title>
	<link rel="stylesheet" href="{{ rtrim(config('fec.asset_base'), '/') }}/templates/system/css/error.css" type="text/css" />
</head>
<body>
	<div class="error">
		<div id="outline">
		<div id="errorboxoutline">
			<div id="errorboxheader">{{ $code }} - {{ $message }}</div>
			<div id="errorboxbody">
			<p><strong>You may not be able to visit this page because of:</strong></p>
			<ol>
				<li>an <strong>out-of-date bookmark/favourite</strong></li>
				<li>a search engine that has an <strong>out-of-date listing for this site</strong></li>
				<li>a <strong>mistyped address</strong></li>
				<li>you have <strong>no access</strong> to this page</li>
				<li>The requested resource was not found.</li>
				<li>An error has occurred while processing your request.</li>
			</ol>
			<p><strong>Please try one of the following pages:</strong></p>
			<ul>
				<li><a href="{{ rtrim(config('fec.site_url'), '/') }}/index.php" title="Go to the Home Page">Home Page</a></li>
			</ul>
			<p>If difficulties persist, please contact the System Administrator of this site and report the error below..</p>
			<div id="techinfo">
				<p>{{ $message }}</p>
				<p>
				</p>
			</div>
			</div>
		</div>
		</div>
	</div>
</body>
</html>
