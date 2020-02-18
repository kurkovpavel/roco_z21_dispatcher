<html>
<head>
<title>Интерфейс управления</title>
<script src="sweetalert2.min.js"></script>
<link rel="stylesheet" href="sweetalert2.min.css">
</head>
<body>
<div class="preloader" text-align="center">
<object class="svgClass" type="image/svg+xml" data="9.svg" height="550px" id="SVG"></object>
</div>
<div id="rightcol">
	<input id="btn_stop" button type="button" class="b1" value="СТОП-кран" onclick="stop()"; ><br><br>
	<input id="btn_restart" button type="button" class="b1" value="Рестарт" onclick="restart()"; > <br><br>
	<input id="btn_shutdown" button type="button" class="b1" value="Завершить работу" onclick="shutdown()"; >
	</div>
<style>
body {
  background-color: white;
  text-align: center;
  padding: 0px;
}

* {
  box-sizing: border-box;
}


/**/
.preloader svg {
  width: 100%;
}

.preloader:hover svg {
  box-shadow: 0 0 0 5px #d3af62;
}

@media (pointer: coarse) {

  .preloader:hover svg {
    box-shadow: 0 0 0 5px transparent;
  }
  
  .preloader:hover:active svg {
    box-shadow: 0 0 0 5px #d3af62;
  }

}
#rightcol {
  position: absolute; 
  right: 10; 
  top: 50px; /
  width: 200px; 
 } 
 
.b1 {
    background: aqua;
    color: blue; 
    font-size: 9pt;
	width:130px;
	height:20px
   }
</style>
<script>
var conn;
var firstway=['1','3','5','7','9','11','12','13'];
var secondway=['17','19','21','22','24'];
var pathfirstway=['1-2','2-3','2-4','4-5','4-6','6-7','6-8','8-9','8-10','10-11','10-12','12-13','13-16','16-1'];
var pathsecondway=['17-18','18-19','18-20','20-21','20-22','22-23','23-24','23-17'];

function stop() {
	Swal.fire({
  title: 'Вы уверены, что хотите снять напряжение с рельс?',
  text: "Отключать напряжение с рельс необходимо в случае аварии. Для возобновления работы потребуется рестарт программы для определения местоположения всех поездов",
  icon: 'Предупреждение',
  showCancelButton: true,
  confirmButtonColor: '#3085d6',
  cancelButtonColor: '#d33',
  confirmButtonText: 'Да, отключить питание',
  cancelButtonText: 'Отмена'
}).then((result) => {
  if (result.value) {
    Swal.fire(
      'Питание отключено!',
      'Контроллеру отправлена команда на снятие напряжения с рельс',
      'success'
    )
	var sendbuf=[0x08,0x00,0x98,0x98,0x00,0x00,0x00,0x00];
	SendCommand(sendbuf);
  }
})
}

function restart() {
	Swal.fire({
  title: 'Вы уверены, что хотите перезапустить программу управления?',
  text: "После запуска программы управления потребуется некоторое время для определения местоположения поездов, при этом все текущие команды будут сброшены",
  icon: 'Предупреждение',
  showCancelButton: true,
  confirmButtonColor: '#3085d6',
  cancelButtonColor: '#d33',
  confirmButtonText: 'Да, перезапустить',
  cancelButtonText: 'Отмена'
}).then((result) => {
  if (result.value) {
    Swal.fire(
      'Программа управления очистила информацию о местоположении поездов и текущих исполняемых командах',
      'Подождите, пока поезда определяют свои местоположения',
      'success'
    )
	var sendbuf=[0x08,0x00,0x97,0x97,0x00,0x00,0x00,0x00];
	SendCommand(sendbuf);
  }
})
}

function shutdown() {
	Swal.fire({
  title: 'Вы уверены, что хотите завершить работу программы управления и отключить питания компьютера?',
  text: "После отключения питания компьютера можно отключить блок управления от сети",
  icon: 'Предупреждение',
  showCancelButton: true,
  confirmButtonColor: '#3085d6',
  cancelButtonColor: '#d33',
  confirmButtonText: 'Да, перезапустить',
  cancelButtonText: 'Отмена'
}).then((result) => {
  if (result.value) {
    Swal.fire(
      'Операционная система завершила работу и отключила web-сервер панели управления',
      'Питание контроллера можно отключить',
      'success'
    )
	var sendbuf=[0x08,0x00,0x96,0x96,0x00,0x00,0x00,0x00];
	SendCommand(sendbuf);
  }
})
}


function SendCommand(name) {
		var arr = new Uint8Array(name);
		conn.send(arr);
}

function notify(evt){
	select_train([0x08,0x00,0xBB],[0x00,0x00,0x00],evt.target.id,evt.target.id.replace(/[^\d;]/g, ''));
	}

function select_train(prefix,code,id_train,id_place) {

if (firstway.includes(id_place)) {
const { value: color } =  Swal.fire({
  title: 'Выберите поезд к назначению', 
  input: 'radio',
    inputOptions: {
    1: 'Поезд № 1',
    2: 'Поезд № 2'
  },
  inputValidator: (value) => {
    if (!value) {
      return 'Вы ничего не выбрали из списка'
    } else
	{
	if (value==1) {
		var sendbuf=prefix.concat([0x31]);
		sendbuf=sendbuf.concat(id_place);
		sendbuf=sendbuf.concat(code);
		SendCommand(sendbuf);
	}	
	if (value==2) {
		var sendbuf=prefix.concat([0x32]);
		sendbuf=sendbuf.concat(id_place);
		sendbuf=sendbuf.concat(code);
		SendCommand(sendbuf);
	}
	}
  }
})
} else {
const { value: color } =  Swal.fire({
  title: 'Выберите поезд к назначению',
  input: 'radio',
    inputOptions: {
    1: 'Поезд № 3'
  },
  inputValidator: (value) => {
    if (!value) {
      return 'Вы ничего не выбрали из списка'
    } else
	{
	if (value==1) {
		var sendbuf=prefix.concat([0x33]);
		sendbuf=sendbuf.concat(id_place);
		sendbuf=sendbuf.concat(code);
		SendCommand(sendbuf);
	}
	}
  }
})
}
}

function connect() {
  conn = new WebSocket('ws://<?php echo $_SERVER['SERVER_ADDR']?>:8000');
  conn.onopen = function() {

  };
  
function set_colors(pl,color) {
for (var prop in firstway) {
  if (document.querySelector(".svgClass").getSVGDocument().getElementById('PL'+firstway[prop]).getAttribute("fill")==color) {
		document.querySelector(".svgClass").getSVGDocument().getElementById('PL'+firstway[prop]).setAttribute("fill", "#FF6633");	
	}
}

for (var prop in secondway) {
  if (document.querySelector(".svgClass").getSVGDocument().getElementById('PL'+secondway[prop]).getAttribute("fill")==color) {
		document.querySelector(".svgClass").getSVGDocument().getElementById('PL'+secondway[prop]).setAttribute("fill", "#FF6633");	
	}
}
	document.querySelector(".svgClass").getSVGDocument().getElementById(pl).setAttribute("fill", color);
	//document.querySelector(".svgClass").getSVGDocument().getElementById('path1-2').setAttribute("fill", color);
}


function set_colors_path(path,newcolor) {
var rm = /(\d+)-(\d+)/gi;
var res1=0;
var res2=0;


for (var prop in pathfirstway) {
	if (document.querySelector(".svgClass").getSVGDocument().getElementById('path'+pathfirstway[prop]).getAttribute("fill")==newcolor) {
		document.querySelector(".svgClass").getSVGDocument().getElementById('path'+pathfirstway[prop]).setAttribute("fill", "cyan");	
		}
	while ((mm = rm.exec(pathfirstway[prop])) !== null) {
		if (mm[1]==path) {
			console.log('path'+pathfirstway[prop]);
			document.querySelector(".svgClass").getSVGDocument().getElementById('path'+pathfirstway[prop]).setAttribute("fill", newcolor);
			res1=1;
		} 
	}
}

for (var prop in pathfirstway) {
	if (res1==0) {
		while ((mm = rm.exec(pathfirstway[prop])) !== null) {
			if (mm[2]==path) {
				console.log('path'+pathfirstway[prop]);
				document.querySelector(".svgClass").getSVGDocument().getElementById('path'+pathfirstway[prop]).setAttribute("fill", newcolor);
				res1=0;
			}
		}
	}
}

for (var prop in pathsecondway) {
	if (document.querySelector(".svgClass").getSVGDocument().getElementById('path'+pathsecondway[prop]).getAttribute("fill")==newcolor) {
		document.querySelector(".svgClass").getSVGDocument().getElementById('path'+pathsecondway[prop]).setAttribute("fill", "cyan");	
		}
	while ((mm = rm.exec(pathsecondway[prop])) !== null) {
		if (mm[1]==path) {
			console.log('path'+pathsecondway[prop]);
			document.querySelector(".svgClass").getSVGDocument().getElementById('path'+pathsecondway[prop]).setAttribute("fill", newcolor);
			res2=1;
		} 
	}
}

for (var prop in pathsecondway) {
	if (res2==0) {
		while ((mm = rm.exec(pathsecondway[prop])) !== null) {
			if (mm[2]==path) {
				console.log('path'+pathsecondway[prop]);
				document.querySelector(".svgClass").getSVGDocument().getElementById('path'+pathsecondway[prop]).setAttribute("fill", newcolor);
				res2=0;
			}
		}
	}
}
}


function parse_answer(data) {
	var re = /([_a-z]\w*)=\s*([^,]*)/gi;
	while ((m = re.exec(data)) !== null) {
	    console.log('Answer:', m[1]+' '+m[2]);
		if ((m[1]=='COM1')&&(m[2]>0)) {
		set_colors('PL'+m[2],"#666600");
		}
		if ((m[1]=='COM2')&&(m[2]>0))  {
		set_colors('PL'+m[2],"#6633FF");
		}
		if ((m[1]=='COM3')&&(m[2]>0))  {
		set_colors('PL'+m[2],"#663333");
		}
		if ((m[1]=='CRP1')&&(m[2]>0))  {
		console.log('PLACE1:'+m[2]);
		
		switch (m[2]) {
			case '1':
			document.querySelector(".svgClass").getSVGDocument().getElementById('TRAIN1').setAttribute('transform','translate(-402,-250)');
			break;
			case '2':
			document.querySelector(".svgClass").getSVGDocument().getElementById('TRAIN1').setAttribute('transform','translate(-468,-340)');
			break;
			case '3':			
			document.querySelector(".svgClass").getSVGDocument().getElementById('TRAIN1').setAttribute('transform','translate(-402,-350)');
			break;
			case '4':
			document.querySelector(".svgClass").getSVGDocument().getElementById('TRAIN1').setAttribute('transform','translate(-387,-490)');
			break;
			case '5':
			document.querySelector(".svgClass").getSVGDocument().getElementById('TRAIN1').setAttribute('transform','translate(-372,-440)');
			break;
			case '6':
			document.querySelector(".svgClass").getSVGDocument().getElementById('TRAIN1').setAttribute('transform','translate(-250,-490)');
			break;
			case '7':
			document.querySelector(".svgClass").getSVGDocument().getElementById('TRAIN1').setAttribute('transform','translate(-230,-440)');
			break;
			case '8':
			document.querySelector(".svgClass").getSVGDocument().getElementById('TRAIN1').setAttribute('transform','translate(-100,-490)');
			break;
			case '9':
			document.querySelector(".svgClass").getSVGDocument().getElementById('TRAIN1').setAttribute('transform','translate(-92,-440)');
			break;
			case '10':
			document.querySelector(".svgClass").getSVGDocument().getElementById('TRAIN1').setAttribute('transform','translate(30,-490)');
			break;
			case '11':
			document.querySelector(".svgClass").getSVGDocument().getElementById('TRAIN1').setAttribute('transform','translate(48,-440)');
			break;
			case '12':
			document.querySelector(".svgClass").getSVGDocument().getElementById('TRAIN1').setAttribute('transform','translate(119-365)');
			break;
			case '13':
			document.querySelector(".svgClass").getSVGDocument().getElementById('TRAIN1').setAttribute('transform','translate(88,-273)');
			break;
			case '14':
			document.querySelector(".svgClass").getSVGDocument().getElementById('TRAIN1').setAttribute('transform','translate(140,-473)');
			break;
			case '15':
			document.querySelector(".svgClass").getSVGDocument().getElementById('TRAIN1').setAttribute('transform','translate(-440,-510)');
			break;
			case '16':
			document.querySelector(".svgClass").getSVGDocument().getElementById('TRAIN1').setAttribute('transform','translate(-485,-235)');
			break;
			}
		set_colors_path(m[2],"#666600");
		}
		if ((m[1]=='CRP2')&&(m[2]>0))  {
		console.log('PLACE2:'+m[2]);
		switch (m[2]) {
			case '1':
			document.querySelector(".svgClass").getSVGDocument().getElementById('TRAIN2').setAttribute('transform','translate(-402,-250)');
			break;
			case '2':
			document.querySelector(".svgClass").getSVGDocument().getElementById('TRAIN2').setAttribute('transform','translate(-468,-340)');
			break;
			case '3':
			document.querySelector(".svgClass").getSVGDocument().getElementById('TRAIN2').setAttribute('transform','translate(-402,-350)');
			break;
			case '4':
			document.querySelector(".svgClass").getSVGDocument().getElementById('TRAIN2').setAttribute('transform','translate(-387,-490)');
			break;
			case '5':
			document.querySelector(".svgClass").getSVGDocument().getElementById('TRAIN2').setAttribute('transform','translate(-372,-440)');
			break;
			case '6':
			document.querySelector(".svgClass").getSVGDocument().getElementById('TRAIN2').setAttribute('transform','translate(-250,-490)');
			break;
			case '7':
			document.querySelector(".svgClass").getSVGDocument().getElementById('TRAIN2').setAttribute('transform','translate(-230,-440)');
			break;
			case '8':
			document.querySelector(".svgClass").getSVGDocument().getElementById('TRAIN2').setAttribute('transform','translate(-100,-490)');
			break;
			case '9':
			document.querySelector(".svgClass").getSVGDocument().getElementById('TRAIN2').setAttribute('transform','translate(-92,-440)');
			break;
			case '10':
			document.querySelector(".svgClass").getSVGDocument().getElementById('TRAIN2').setAttribute('transform','translate(30,-490)');
			break;
			case '11':
			document.querySelector(".svgClass").getSVGDocument().getElementById('TRAIN2').setAttribute('transform','translate(48,-440)');
			break;
			case '12':
			document.querySelector(".svgClass").getSVGDocument().getElementById('TRAIN2').setAttribute('transform','translate(119-365)');
			break;
			case '13':
			document.querySelector(".svgClass").getSVGDocument().getElementById('TRAIN2').setAttribute('transform','translate(88,-273)');
			break;
			case '14':
			document.querySelector(".svgClass").getSVGDocument().getElementById('TRAIN2').setAttribute('transform','translate(140,-473)');
			break;
			case '15':
			document.querySelector(".svgClass").getSVGDocument().getElementById('TRAIN2').setAttribute('transform','translate(-440,-510)');
			break;
			case '16':
			document.querySelector(".svgClass").getSVGDocument().getElementById('TRAIN2').setAttribute('transform','translate(-485,-235)');
			break;
			}
		set_colors_path(m[2],"#6633FF");
		}
		if ((m[1]=='CRP3')&&(m[2]>0))  {
		console.log('PLACE3:'+m[2]);
		switch (m[2]) {
			case '17':
			document.querySelector(".svgClass").getSVGDocument().getElementById('TRAIN3').setAttribute('transform','translate(-398,-150)');
			break;
			case '18':
			document.querySelector(".svgClass").getSVGDocument().getElementById('TRAIN3').setAttribute('transform','translate(-342,-113)');
			break;
			case '19':
			document.querySelector(".svgClass").getSVGDocument().getElementById('TRAIN3').setAttribute('transform','translate(-272,-122)');
			break;
			case '20':
			document.querySelector(".svgClass").getSVGDocument().getElementById('TRAIN3').setAttribute('transform','translate(-342,-180)');
			break;
			case '21':
			document.querySelector(".svgClass").getSVGDocument().getElementById('TRAIN3').setAttribute('transform','translate(-272,-198)');
			break;
			case '22':
			document.querySelector(".svgClass").getSVGDocument().getElementById('TRAIN3').setAttribute('transform','translate(-120,-188)');
			break;
			case '23':
			document.querySelector(".svgClass").getSVGDocument().getElementById('TRAIN3').setAttribute('transform','translate(-140,-290)');
			break;
			case '24':
			document.querySelector(".svgClass").getSVGDocument().getElementById('TRAIN3').setAttribute('transform','translate(-232,-350)');
			break;
			}
		set_colors_path(m[2],"#663333");
		}
	}

}
 

  conn.onmessage = function(e) {
    console.log('Message:', e.data);
	parse_answer(e.data);
  };

  conn.onclose = function(e) {
    console.log('Socket is closed. Reconnect will be attempted in 1 second.', e.reason);
    setTimeout(function() {
      connect();
    }, 1000);
  };

  conn.onerror = function(err) {
    console.error('Socket encountered error: ', err.message, 'Closing socket');
    conn.close();
  };
}

	window.onload = function() {
		connect();
	}
  


 </script>
</body>
</html>  
