{* template block that contains the new field *}
<table>
<tr id="membership_row">
	<td class="label">{$form.membershipselect.label}</td>
  <td id="membership_field">{$form.membershipselect.html}</td>
</tr>
</table>
{* reposition the above block after #someOtherBlock *}
<script type="text/javascript">
{literal}
cj("#membership_row").appendTo("table.form-layout:first").hide();
cj('document').ready(function(){
	if(cj('#html_type').val() == 'gift_membership'){
			cj('#showoption').hide();
			cj('#membership_row').show();
			cj('#price').parent().parent().show();
			cj('#price-block').show();
		} else if(cj(this).val() == 'redeem_membership') {
			cj('#showoption').hide();
			cj('#is_display_amounts').val('0').attr("checked", false);
			cj('#is_display_amounts').parent().parent().hide();
			cj('#price-block').hide();
			cj('#membership_row').hide(); 
			cj('#price').val("0.00");
		} else {
			cj('#price').val('');
			cj('#price').parent().parent().show();
			cj('#membership_row').hide();
			cj('#price-block').show();
		}
	cj('#html_type').change(function(){
		if(cj(this).val() == 'gift_membership'){
			cj('#showoption').hide();
			cj('#price-block').show();
			cj('#membership_row').show();
		} else if(cj(this).val() == 'redeem_membership') {
			cj('#showoption').hide();
			cj('#is_display_amounts').val('0').attr("checked", false);
			cj('#is_display_amounts').parent().parent().hide();
			cj('#price-block').hide();
			cj('#membership_row').hide();
			cj('#price').val("0.00");
		} else {
			cj('#price-block').show();
			cj('#price').parent().parent().show();
			cj('#membership_row').hide();
		}
	});
	cj('form').submit(function(){
		if(cj('#html_type').val() == 'gift_membership'){
		 cj('#html_type').val('Text');
		 cj('input[name="gift-check"]').val(1);
		} else if(cj('#html_type').val() == 'redeem_membership'){
		 cj('#html_type').val('Text');
		 cj('input[name="redeem-check"]').val(1);
		}
	});
});
{/literal}
</script>
