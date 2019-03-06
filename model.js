var BackDrop = null;
var DragGroup = null;

var PointGrabbed = null;
var GroupOrigin = null;
var SVGRoot = null;
var pt = null;
var reload_timer_handle = null;

function click(evt){
	var href = location.href.replace(/\/\d*$/,'').replace(/[^\/]*$/,''); // first: strip trailing number, if present. then: strip page
	if (evt.target.id == 'backdrop') return;
	location.href = href + evt.target.id.replace(/_([^_]*)$/,'/$1');
}

function crossHair(x,y,text){
	var vl = document.createElementNS('http://www.w3.org/2000/svg','line');
	vl.setAttribute('x1',x);
	vl.setAttribute('y1',y-10);
	vl.setAttribute('x2',x);
	vl.setAttribute('y2',y+10);
	vl.setAttribute('class','arrow');
	SVGRoot.appendChild(vl);
	var hl = document.createElementNS('http://www.w3.org/2000/svg','line');
	hl.setAttribute('x1',x-10);
	hl.setAttribute('y1',y);
	hl.setAttribute('x2',x+10);
	hl.setAttribute('y2',y);
	hl.setAttribute('class','arrow');
	SVGRoot.appendChild(hl);
	var txt = document.createElementNS('http://www.w3.org/2000/svg','text');
	txt.setAttribute('x',x);
	txt.setAttribute('y',y-10);
	txt.setAttribute('class','left');
	txt.innerHTML = text;
	SVGRoot.appendChild(txt);
}

function drag(evt){
	// if we don't currently have an element in tow, don't do anything
	if (DragGroup){
		var cp = clickPos(evt);
		var x = GroupOrigin.x + cp.x - PointGrabbed.x;
		var y = GroupOrigin.y + cp.y - PointGrabbed.y;
		DragGroup.setAttributeNS(null, 'transform', 'translate(' + x + ',' + y + ')');
	}
}

function drop(evt){
	// if we aren't currently dragging an element, don't do anything
	if ( DragGroup )	{
		DragGroup.setAttributeNS(null, 'pointer-events', 'all'); // turn the pointer-events back on, so we can grab this item later
		var elem = getMainComponent(DragGroup);
		if (elem != null){
			var cp = clickPos(evt);
			var moveX = cp.x - PointGrabbed.x;
			var moveY = cp.y - PointGrabbed.y;
			if (Math.abs(moveX) < 5 && Math.abs(moveY)<5) { // if not dragged: handle as click
								
				location.href = model_base + elem.id + '/view';
			} else {
				var x = GroupOrigin.x + moveX;
				var y = GroupOrigin.y + moveY;
				updateElement(elem,{x: x, y: y});
			}
		}
		DragGroup = null;
	}
}

function getMainComponent(group){
	var children = group.children;
	for (var i=0; i<children.length; i++){
		var child = children[i];
		if (child.hasAttribute('id')) return child;
	}
	return null;
}

function getTranslation(elem){
	if (!elem.hasAttribute('transform')) return {x:0, y:0};
	var trans = elem.getAttribute('transform');
	var parts  = /translate\(\s*([^\s,)]+)[ ,]([^\s,)]+)/.exec(trans);
	return {x:+parts[1], y:+parts[2]};
}

function clickPos(evt){
	pt.x = evt.clientX;
	pt.y = evt.clientY;
	var cursorpt =  pt.matrixTransform(SVGRoot.getScreenCTM().inverse());
	return {x:cursorpt.x,y:cursorpt.y};
}

function grab(evt){
	if (evt.button != 0) return; // only respond to right button
	if (evt.target == BackDrop) return; // don't drag the background

	if (evt.target.getAttribute('class') == 'connector') return; // don't drag connectors
	
	DragGroup = evt.target;
	// only move groups
	while (DragGroup.nodeName != 'g'){
		DragGroup = DragGroup.parentNode;
		if (DragGroup == null) return;
	}
	if (DragGroup.getAttribute('class') == 'arrow') return; // don't drag connectors
	
	if (reload_timer_handle != null) clearTimeout(reload_timer_handle);
	
	// move this element to the "top" of the display, so it is (almost)
	DragGroup.parentNode.appendChild( DragGroup );

	// turn off all pointer events to the dragged element, this does 2 things:
	DragGroup.setAttributeNS(null, 'pointer-events', 'none');
	var cp = clickPos(evt);
	PointGrabbed = {x: cp.x, y: cp.y};
	GroupOrigin = getTranslation(DragGroup);
}

function initSVG(evt){
	SVGRoot = evt.target;
	pt = SVGRoot.createSVGPoint();
	BackDrop = evt.target.ownerDocument.getElementById('backdrop');
}

function presetConnectorName(elem){
	var id = $("input[name=process_id]").attr('value');
	var out=elem.value;
	var input = $("input[name=name]");
	var start=id.length+1;
	input.attr('value',id+(out == 1?':out':':in'));
	input.each(function(){
		this.focus();
		this.selectionStart = start;
		this.selectionEnd = 1000;
	});
}
function schedule_reload(){
	if (reload_timer_handle != null) clearTimeout(reload_timer_handle);
//	reload_timer_handle = setTimeout(function(){location.reload()},750);
}

function updateElement(elem,data){
	if (elem.hasAttribute('place_id')) data['place_id'] = elem.getAttribute('place_id');
	$.ajax({
		url: model_base+'update_'+elem.getAttribute('class')+'/'+elem.id,
		method: 'POST',
		data: data,
		complete: function(a,b){
			schedule_reload();
		}
	});
}

function wheel(evt){
	var elem = evt.target;
	var cls = elem.getAttribute('class'); 

	if (evt.target.nodeName == 'circle'){

		if (cls == 'process' && evt.shiftKey){
			evt.preventDefault();
			var r = elem.getAttribute('r')-10*Math.sign(evt.deltaY);
			if (r>10) {
				elem.setAttribute('r',r);
				updateElement(elem,{r: r});
			}
		} else if(cls == 'connector'){
			evt.preventDefault();
			var xforms = elem.getAttribute('transform');

			var parts  = /rotate\(\s*([^\s,)]+)[ ,]([^\s,)]+)[ ,]([^\s,)]+)/.exec(xforms);
			var da = 10*Math.sign(evt.deltaY);			
			var a = +parts[1];
			if (a<180) {
				a += da;
			} else a -= da;
			while (a<0) a+=360;
			while (a>=360) a-=360;
			var x = +parts[2];
			var y = +parts[3];
			elem.setAttribute('transform','rotate('+a+','+x+','+y+')');
			updateElement(elem,{angle: a});
		}
	} else {
		if (cls == 'terminal' && evt.shiftKey){
			evt.preventDefault();
			var d = -10*Math.sign(evt.deltaY);
			var w = +elem.getAttribute('width')+d;
			if (w>10) {
				elem.setAttribute('width',w);


				var texts = elem.parentNode.getElementsByTagName('text');
				if (texts.length > 0){
					var text = texts[0];
					text.setAttribute('x',+text.getAttribute('x')+d/2);
				}
				var ellipses = elem.parentNode.getElementsByTagName('ellipse');
				for (var i=0; i<ellipses.length;i++){
					var ellipse = ellipses[i];
					ellipse.setAttribute('cx',+ellipse.getAttribute('cx')+d/2)
					ellipse.setAttribute('rx',+ellipse.getAttribute('rx')+d/2)
					elem.setAttribute('stroke-dasharray','0,'+w+',40,'+w+',40');
				}
				updateElement(elem,{w: w});
			}
		}
	}
}