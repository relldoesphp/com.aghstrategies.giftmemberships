// JavaScript Document
cj('form').submit(function(){
	cj("._gift_membership-content input").each(function(){
		var quanity = cj(this).val();
		var inputname = cj(this).attr('name');
		var pfid = inputname.replace('price_', '');
		var codeId = pfid+"_gift-codes";
		var codes = "";
		for ( var i = 0; i < quanity; i++ ) {
      codes += Math.random().toString(36).substring(8)+"::";
    }
    codes = codes.slice(0, -2);
    cj("input[name="+codeId+"]").val(codes);
	});
});
