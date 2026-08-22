$(function() {
	"use strict";

	// Toggle sidebar
	$("#closebtn").on("click", function(e) {
		$('#mySidebar').css("width", "0");
		$('#main').css("margin-left", "0");
		$(this).hide();
		$("#openbtn").show();
	});

	$("#openbtn").on("click", function(e) {
		$('#mySidebar').css("width", "280px");
		$('#main').css("margin-left", "280px");
		$(this).hide();
		$("#closebtn").show();
	});

	// Auto-collapse sidebar on small screens
	if ($(window).width() < 768) {
		$('#mySidebar').css("width", "0");
		$('#main').css("margin-left", "0");
		$("#closebtn").hide();
		$("#openbtn").show();
	}

	// Table of Contents generation
	var toc = $("#toc").tocify({
		selectors: "h2,h3,h4,h5",
		scrollTo: 80,
		highlightOnScroll: true,
		smoothScroll: true,
		smoothScrollSpeed: 400,
		showEffect: "fadeIn",
		showEffectSpeed: 200,
		extendPage: false
	}).data("toc-tocify");
});