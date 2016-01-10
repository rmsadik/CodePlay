//javascript countdown timer
function Countdown(options) {
	var timer,
		instance = this,
		seconds = options.seconds || 10,
		updateStatus = options.onUpdateStatus || function() {},
		counterEnd = options.onCounterEnd || function() {};

	function decrementCounter() {
		updateStatus(seconds);
		if (seconds === 0) {
			counterEnd();
			instance.reset()
		}
		seconds--;
	}

	this.start = function() {
		clearInterval(timer);
		timer = 0;
		seconds = options.seconds;
		timer = setInterval(decrementCounter, 1000);
	};

	this.stop = function() {
		clearInterval(timer);
	};

	this.reset = function() {
		seconds = options.seconds;
	};
}