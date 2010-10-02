<html>
<head>
<title>Fossology License Test Results</title>
</head>
<body>
<table border="1" cellpadding="0">
        <tr>
                <td colspan="4" align="center"><strong>Fossology License Test Results</strong></td>
        </tr>
  <tr>
    <th align="center" colspan="2">Nomos</th>
    <th align="center" colspan="2">BSam</th>
    <!-- <th align="center" colspan="2">FoNomos</th> -->
  </tr>
  <tr>
    <th align="center">File</th>
    <th align="center">Vetted Name</th>
    <th align="center">Pass</th>
    <th align="center">Fail</th>
  </tr>   
    </tr>
    {assign var=cntr value=0}
    {section name=tr loop=$file}
  <tr>
      <td align="left">{$file[tr]}</td>
      <td align="center">{$vetted[tr]}</td>
      {section name=td loop=$results max=2}
         {assign var=gre value=`$cntr%2`}
         {if $results[$cntr] ne ''}
           {* pass=0, fail=1*}
           {if $gre eq 0}
             <td align="center" style="color:#009900">{ $results[$cntr] }</td>
           {elseif $gre eq 1}
             <td align="center" style="color:red">{ $results[$cntr] }</td>
           {/if}
         {else}
           <td align="center">{"&nbsp;"}</td>
         {/if}
        {assign var=cntr value=`$cntr+1`}
      {/section}
  </tr>
    {/section}
</table>

</body>
</html>

    