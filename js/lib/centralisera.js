
let disabled = true;

document.getElementById("conditions_to_approve[terms-and-conditions]").onchange = function() {

	let element = document.getElementById("centraliseraPayBtn");

	if(element.classList.contains('disabled')) {
		element.classList.remove("disabled");
	}else {
		element.classList.add("disabled");
	}

}
