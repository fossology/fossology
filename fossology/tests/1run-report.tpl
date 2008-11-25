<html>
<head>
<title>Fossology Test Results</title>
</head>
<body>
<h2>Latest Test Results 2008-11-24 svn version 1719</h2>
<table border="1">
	<th>Test/Date</th><th>Passes</th><th>Failures</th><th>Exceptions</th><th>Elapsed Time</th>
	<!--
	<tr><td align="left">{$test_name}</td><td align="center">{$passes}</td>
	<td align="center">{$failures}</td><td align="center">{$exceptions}</td><td align="center">{$etime}</td>
	</tr>
	-->
	{section name=tr loop=$results step=$cols}
  <tr>
    {section name=td start=$smarty.section.tr.index loop=$smarty.section.tr.index+$cols}
    <td align="center">{$results[td]|default:"& nbsp;"}</td>
    {/section}
  </tr>
  {/section}
</table>
<h3>Test Run Notes</h3>
<p>
The 1 failure is expected.  It is the duplicated upload test....
</body>
</html>
