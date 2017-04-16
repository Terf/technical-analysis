<?php

/**
 * Technical analysis for traders
 *
 * Whereas fundamental analysis focuses on a securities fundamentals i.e. a good product, competent management, etc., technical analysis looks for patterns in historical data
 * This algorithm attempts to find under/over values securities using technical analysis
 * Additionally, the success of this algorithm is a test of the validity of the Efficient Market Hypothesis (EMH) which states that all assets reflect all available information thus it is therefore impossible to find over/under values securities, as well as whether technical analysis can predict future price movements as technical analysis does not enjoy as much widespread acceptance as fundamental analysis
 * A negative rating means the security is overbought (and should be sold) while a positive rating indicates a security is underbought (and should be bought)
 * The more extreme the rating, the more confident the rating is
 * 
 * @author Tim Robert-Fitzgerald <trobertf@oberlin.edu>
 * @version 1.0
 * Requires PHP 5.4+
*/
class Trader {

  /**
  * Older data must have the lower index in the array and newer data must have the highest index in the array
  *
  * @param $open prices
  * @param $high prices
  * @param $low prices
  * @param $close prices
  * @param $volume
  */
  public function __construct($open, $high, $low, $close, $volume) {
    if (!is_array($open) || !is_array($high) || !is_array($low) || !is_array($close) || !is_array($volume)) {
      throw new Exception("\$open, \$high, \$low, \$close, and \$volume data must be array");
    }
    $this->rating = 0;
    $this->notes = array();
    $this->open = $open;
    $this->high = $high;
    $this->low = $low;
    $this->close = $close;
    $this->volume = $volume;    
  }

  /**
  * Slope of a line
  *
  * @param $array to be calculated
  * @return $slope
  */
  private function m($array) {
    $x1 = 0;
    $y1 = $array[0];
    $x2 = count($array);
    $y2 = end($array);
    return ($y1 - $y2) / ($x1 - $x2);
  }

  /**
  * Pearson correlation coefficient
  * Measures the correlation between two variables (in this case time and price)
  * Negative values point to negative correlations, positive values point to positive correlations, 0 means no correlation
  *
  * @param Array of $prices to be calculated
  * @return Pearson's $r
  */
  private function pearson($prices) {
    $n = count($prices);
    $x = $prices;
    for ($i = 0; $i < $n; $i++) {
      $y[$i] = $i + 1;
    }
    for ($i = 0; $i < $n; $i++) {
      $xy[$i] = $x[$i] * $y[$i];
      $x2[$i] = $x[$i] * $x[$i];
      $y2[$i] = $y[$i] * $y[$i];
    }
    $sum_x = array_sum($x);
    $sum_y = array_sum($y);
    $sum_xy = array_sum($xy);
    $sum_x2 = array_sum($x2);
    $sum_y2 = array_sum($y2);
    $numerator = $n * ($sum_xy) - ($sum_x) * ($sum_y);
    $denominator = sqrt( ($n * $sum_x2 - ($sum_x * $sum_x)) * ($n * $sum_y2 - ($sum_y * $sum_y)) );
    $r = $numerator / $denominator;
    return $r;
  }

  /**
  * Standard deviation
  *
  * @param $array of values
  * @return Standard variation
  */
  private function sd($array) {
    $n = count($array);
    $mean = array_sum($array) / $n;
    $variance = array();
    foreach ($array as $value) {
      array_push($variance, pow($value - $mean, 2));
    }
    return sqrt(array_sum($variance) / $n);
  }

  /**
  * Average True Range (ATR) measures volatility
  * Because ATR measures volatility and does not consider price direction, it is not used to predict future moves (and is a private function because it is not useful by itself)
  * To calculate the ATR, the True Range must be calculated first. The True Range is the max((high - low), abs(high - previous close), abs(low - previous close))
  * The ATR time t is ATR(t) = ATR(t - 1) * (n - 1) + TR(t) / n
  *
  * @param $high prices
  * @param $low prices
  * @param $close prices
  * @return Multidimensional array of calculated TR and ATR values (first TR, second ATR)
  */
  private function atr($high, $low, $close) {
    $tr = array($high[0] - $low[0]);
    for ($i = 1; $i < count($high); $i++) { 
      array_push($tr, max(($high[$i] - $low[$i]), abs($high[$i] - $close[$i - 1]), abs($low[$i] - $close[$i - 1])));
    }
    $atr = array(array_sum(array_slice($tr, 0, 13)) / 13);
    for ($i = 14; $i < count($tr); $i++) { 
      array_push($atr, ($atr[$i - 14] * 13 + $tr[$i]) / 14);
    }
    return array($tr, $atr);
  }

  /**
  * Simple Moving Average (SMA) is the average of (equally weighted) data points over a given period of time and is used to smooth out noise in a chart and reveal underlying trends
  * Moving averages always take the most recent data points, dropping old ones as new ones become available
  * Note that moving averages normally take closing prices
  *
  * @param $array of values to be calculated
  * @param $time_period to be used
  * @return Array of calculated values, oldest to newest
  */
  private function sma($array, $time_period) {
    if ($time_period > count($array)) {
      throw new Exception("Time period greater than data set in call to sma(\$array, $time_period)");
    }
    $result = array();
    for ($i = 0; $i <= (count($array) - $time_period); ++$i) {
      // Divide the sum of the most recent slice of the array by the time period and add on to $result
      array_push($result, (array_sum(array_slice($array, $i, $time_period)) / $time_period));
    }
    return $result;
  }

  /**
  * Like the SMA, the Exponential Moving Average (SMA) is the average of data points over a given period of time, but the EMA gives more weight to more recent data points
  * Because the EMA treats more recent data as more relevant, it is quicker to respond to price changes, but is also prone to give false buy/sell signals because of random price fluctuations
  *
  * @param $array of values to be calculated
  * @param $time_period to be used
  * @return Array of calculated values, oldest to newest
  */
  private function ema($array, $time_period) {
    $prev_ema = $this->sma($array, $time_period)[0];
    $smoothing_constant = 2 / ($time_period + 1);
    $result = array();
    array_push($result, $prev_ema);
    for ($i = 0; $i < (count($array) - $time_period); ++$i) {
      // {Close - EMA(previous day)} x multiplier + EMA(previous day)
      $ema = $smoothing_constant * ($array[$i + $time_period] - $prev_ema) + $prev_ema;
      array_push($result, $ema);
      $prev_ema = $ema;
    }
    return $result;
  }

  /**
  * Divergence
  * Because divergences are a common thing to look for in an indicators chart, this helper function determines if a divergence between $up and $down exists
  *
  * @param The supposedly $up(ward) trending array
  * @param The supposedly $down(ward) trending array
  * @param $r The strength of each trend (valid range: 0-1)
  * @return TRUE if $up is trending up and $down is trending down by at least $r, FALSE otherwise
  */
  private function is_divergence($up, $down, $r=0.5) {
    if ($r > 1 || $r < 0) {
      throw new Exception("$r needs to be in range 0-1 when determining divergence existance");
    }
    elseif ($this->pearson($up) > $r && $this->pearson($down) < -$r) {
      return TRUE;
    }
    return FALSE;
  }

  /**
  * Moving average convergence divergence (MACD) is one of the most straightforward technical indicators and considers the difference between long and short term price trends
  * When a new trend in price starts to occur and picks up momentum, the faster moving average (the one that considers a shorter time period) starts to diverge from the slower moving average. The MACD attempts to measure and profit from this fact
  * Although alternatives to the (12, 26, 9) time period can be used, they are somewhat unusual and (12, 26, 9) has been hardcoded in both for simplicity and because alternative time frames are not used by this algorithim
  * Because of the similarity the MACD has with the Percentage Price Oscillator (PPO) and TRIX, the PPO and TRIX have been omitted
  * MACD = 12 day EMA - 26-day EMA
  * Signal Line = 9-day EMA of MACD Line
  * MACD Histogram = MACD Line - Signal Line
  *
  * @param $array of prices to be calculated
  * @return Multidimensional array of calculated MACD values (the first array is the MACD values, the second is the signal line, the third is the MACD histogram)
  */
  protected function macd($array) {
    $ema_26 = $this->ema($array, 26);
    $ema_12 = array_slice($this->ema($array, 12), 14); // The first 14 (26 - 12) values are irrelevant
    $result = array(array());
    // MACD
    for ($i = 0; $i < count($ema_26); ++$i) { 
      array_push($result[0], $ema_12[$i] - $ema_26[$i]);
    }
    // Signal line
    array_push($result, $this->ema($result[0], 9));
    // MACD Histogram
    array_push($result, array());
    for ($i = 0; $i < count($result[1]); ++$i) { 
      array_push($result[2], $result[0][$i + 8] - $result[1][$i]);
    }
    return $result;
  }

  /**
  * Signals can be inferred from the MACD through
  * - Signal line crossovers
  *   When the MACD line crosses above the signal line (the MACD histogram switches from negative to positive values) it is bullish
  *   When the MACD line crosses below the signal line (the MACD histogram switches from positive to negative values) it is bearish
  * - Zero line crossovers
  *   Similiar to the signal line crossover, but occurs when the MACD line crosses the zero line (becomes negative/positive)
  *   A bullish zero crossover is when the MACD goes from negative to positive, a bearish signal is when the MACD goes from positive to negative
  * - Divergences
  *   When price and the MACD do not agree
  *   Because MACD divergences are difficult for computers to spot and have proven inaccurate in tests, they have been omitted
  */
  public function macd_signal() {
    $close = $this->close;
    $macd = $this->macd($close);
    // Signal-line crossover
    $histogram15 = array_slice($macd[2], -15);
    if ($this->pearson($histogram15) > 0.6 && $histogram15[0] < 0 && end(array_values($histogram15)) > 0 && $this->m($histogram15) > 0.035) { // If histogram trends up, passing 0 on the way
      $this->rating += 5;
      array_push($this->notes, "Bullish signal line crossover found");
    }
    elseif ($this->pearson($histogram15) < -0.6 && $histogram15[0] > 0 && end(array_values($histogram15)) < 0 && $this->m($histogram15) < -0.035) { // If histogram trends down, passing 0 on the way
      $this->rating -= 5;
      array_push($this->notes, "Bearish signal line crossover found");
    }
    // Zero crossover
    $macd15 = array_slice($macd[0], -15);
    if ($this->pearson($macd15) > 0.6 && $macd15[0] < 0 && end(array_values($histogram15)) > 0 && $this->m($macd15) > 0.05) { // MACD crosses 0 line with sufficient slope
      $this->rating += 5;
      array_push($this->notes, "Bullish zero line crossover found");
    }
    elseif ($this->pearson($macd15) < -0.6 && $macd15[0] > 0 && end(array_values($histogram15)) < 0 && $this->m($macd15) < -0.05) { // MACD crosses 0 line with sufficient slope
      $this->rating -= 5;
      array_push($this->notes, "Bearish zero line crossover found");
    }
  }

  /**
  * The Stochastic Oscillator compares a securities closing price to its price range over a given period of time (normally 14 [days/weeks/months])
  * It is based on the theory that as a securities price increases/decreases, it will close at its highest/lowest point
  * According to George C. Lane, who developed the theory in the late 1950s, the oscillator "doesn't follow price, it doesn't follow volume or anything like that. It follows the speed or the momentum of price. As a rule, the momentum changes direction before price."
  * For its similiartiy to Williams %R, %R has been omitted from this algorithim
  * The oscillator is based on the calculation of two numbers:
  * %K = (Current Close - Lowest Low)/(Highest High - Lowest Low) * 100
  * %D = 3-day SMA of %K
  * Where Lowest the lowest low = lowest low for the look-back period (usually 14) and the highest high = highest high for the look-back period (usually 14)
  *
  * @param $high prices
  * @param $low prices
  * @param $close prices
  * @return Multidimensional array of calculated %K and %D values (the first array is the %K, the second is the %D)
  */
  protected function stoch($high, $low, $close) {
    $stoch = array(array());
    for ($i = 0; $i < (count($high) - 14); $i++) { 
      array_push($stoch[0], (((end(array_values(array_slice($close, $i, 14))) - min(array_slice($low, $i, 14))) / (max(array_slice($high, $i, 14)) -  min(array_slice($low, $i, 14)))) * 100));
    }
    array_push($stoch, $this->sma($stoch[0], 3));
    return $stoch;
  }

  /**
  * Signals can be inferred from the stochastic oscillator through
  * - Overbought/oversold levels
  *   When the stochastic oscillator returns values below 20 the security is thought to be oversold, while readings above 80 the security is overbought
  * - Divergences
  *   When a new high/low in price is not matched by a new high/low in the stochastic oscillator
  *   Bullish divergence forms when prices move to new lows, but the Stochastic Oscillator moves up
  *   Bearish divergence forms when price records new highs, but the Stochastic Oscillator goes down
  * - Bull/bear setup
  *   Inverse of divergences
  *   Bull Setup occurs when price records a lower high, but Stochastic records a higher high. The setup then results in a dip in price which can be seen as a Bullish entry point before price rises. See: https://www.tradingview.com/stock-charts-support/index.php/Stochastic#Bull.2FBear_Setups
  *   Bear Setup occurs when price records a higher low, but Stochastic records a lower low. The setup then results in a bounce in price which can be seen as a Bearish entry point before price falls.
  */
  public function stoch_signal() {
    $high = $this->high;
    $low = $this->low;
    $close = $this->close;
    $stoch = $this->stoch($high, $low, $close);
    $k = $stoch[0];
    $d = $stoch[1];
    $curr_k = end(array_values($k));
    $curr_d = end(array_values($d));
    // Overbought/oversold levels
    if ($curr_k > 80 && $curr_d > 80) { // %D and %K are above 80; security is overbought and should be sold
      $this->rating -= pow(($curr_k - 80) / 10, 4) + 5; // Security is overbought; subtract from signal based on magnitude of %K (range: 5-21)
      array_push($this->notes, "Bearish Stochastic reading (%K at $curr_k, %D at $curr_d)");
    }
    if ($curr_k < 20 && $curr_d < 20) { // %D and %K are below 20; security is oversold and should be bought
      $this->rating += pow((((-$curr_k) + 20) / 10), 4) + 5; // Security is oversold; add to signal based on magnitude of %K (range: 5-21)
      array_push($this->notes, "Bullish Stochastic reading (%K at $curr_k, %D at $curr_d)");
    }
    // Divergences
    if ($this->is_divergence(array_slice($k, -5), array_slice($close, -5), 0.3)) { // If the last week of prices are trending downward and the last week of %K is trending upward
      $this->rating += 5;
      array_push($this->notes, "Bullish Stochastic divergence found");
    }
    elseif ($this->is_divergence(array_slice($close, -5), array_slice($k, -5), 0.3)) { // If the last week of prices are trending upward and the last week of %K is trending downward
      $this->rating -= 5;
      array_push($this->notes, "Bearish Stochastic divergence found");
    }
    // Bull/bear setup
    if ($this->pearson(array_slice($close, -15)) < -0.3 && $this->pearson(array_slice($k, -15)) > 0.3) { // If prices are moving down, and price/%K are converging
      array_push($this->notes, "Stochastic Oscillator found a bull set up. Momentum is changing; expect price to soon make a new low before rebounding.");
    }
    elseif ($this->pearson(array_slice($close, -15)) > 0.3 && $this->pearson(array_slice($k, -15)) < -0.3) {
      array_push($this->notes, "Stochastic Oscillator found a bear set up. Momentum is changing; expect price to soon make a new high before falling.");
    }
  }

  /**
  * The Relative Strenth Index (RSI) is a momentum oscillator used to measure the strength/weakness of a security to determine if it is underbought/undersold
  * The RSI oscillates between 0 and 100; when it goes above 70 the security is thought to be overbought, and when it drops below 30 it is thought to be oversold
  * The RSI is normally based on 14 periods
  * RSI = 100 - (100 / 1 + RS) where RS = Average gain / Average loss
  * First Average Gain = Sum of Gains over the past 14 periods / 14
  * First Average Loss = Sum of Losses over the past 14 periods / 14
  * Average Gain = [(previous Average Gain) * 13 + current Gain] / 14
  * Average Loss = [(previous Average Loss) * 13 + current Loss] / 14
  *
  * @param $close prices to be calculated
  * @return Array of calculated RSI values
  */
  protected function rsi($close) {
    if (count($close) < 15) {
      throw new Exception("RSI requires at least 15 periods");
    }
    $gains = array();
    $losses = array();
    $last_close = $close[0];
    foreach ($close as $value) {
      if ($last_close > $value) {
        array_push($losses, ($last_close - $value));
        array_push($gains, NULL);
      }
      elseif ($last_close < $value) {
        array_push($gains, ($value - $last_close));
        array_push($losses, NULL);
      }
      else {
        array_push($gains, NULL);
        array_push($losses, NULL);
      }
      $last_close = $value;
    }
    $prev_avg_gain = array_sum(array_slice($gains, 0, 14)) / 14;
    $prev_avg_loss = array_sum(array_slice($losses, 0, 14)) / 14;
    $avg_gains = array($prev_avg_gain);
    $avg_losses = array($prev_avg_loss);
    for ($i = 0; $i < (count($close) - 15); $i++) {
      $avg_gain = (($prev_avg_gain * 13) + $gains[$i + 15]) / 14;
      $avg_loss = (($prev_avg_loss * 13) + $losses[$i + 15]) / 14;
      array_push($avg_gains, $avg_gain);
      array_push($avg_losses, $avg_loss);
      $prev_avg_gain = $avg_gain;
      $prev_avg_loss = $avg_loss;
    }
    $rs = array();
    for ($i = 0; $i < count($avg_gains); $i++) { 
      array_push($rs, ($avg_gains[$i] / $avg_losses[$i]));
    }
    $rsi = array();
    for ($i = 0; $i < count($rs); $i++) { 
      array_push($rsi, 100 - (100 / (1 + $rs[$i])));
    }
    return $rsi;
  }

  /**
  * Signals can be inferrred from the RSI through
  * - Overbough/oversold levels
  *   When the RSI returns values below 30 the security is thought to be oversold, while readings above 70 are thought to indicate that the security is overbought
  * - Divergences
  *   A bullish divergence is when price makes a new low but RSI makes a higher low; a bearish divergence is when price makes a new high but RSI makes a lower high
  * - Failure swings
  *   Failure swings occur when the RSI bounces between oversold/overbought conditions, but is difficult to program, and has been left out
  * Note: In tests, RSI has proven very effective at finding under/overvalued stocks (correct ~60% of time)
  */
  public function rsi_signal() {
    $close = $this->close;
    $rsi = $this->rsi($close);
    $last_rsi = end(array_values($rsi));
    // Overbought/oversold levels
    if ($last_rsi > 70) { // RSI is above 70; security is overbought and should be sold
      $this->rating -= pow(($last_rsi - 70) / 15, 4) + 30; // Security is overbought; subtract from rating based on magnitude of RSI (range: 30 - 46)
      array_push($this->notes, "Bearish RSI reading ($last_rsi)");
    }
    if ($last_rsi < 30) { // RSI is below 30; security is oversold and should be bought
      $this->rating += pow((((-$last_rsi) + 30) / 15), 4) + 30; // Security is oversold; add to rating based on magnitude of RSI (range: 30 - 46)
      array_push($this->notes, "Bullish RSI reading ($last_rsi)");
    }
    // Divergences
    if ($this->is_divergence(array_slice($rsi, -5), array_slice($close, -5), 0.4)) { // If the last week of prices are trending downward and the last week of RSI is trending upward
      $this->rating += 15;
      array_push($this->notes, "Bullish RSI divergence found");
    }
    elseif ($this->is_divergence(array_slice($close, -5), array_slice($rsi, -5), 0.4)) { // If the last week of prices are trending upward and the last week of RSI is trending downward
      $this->rating -= 15;
      array_push($this->notes, "Bearish RSI divergence found");
    }
  }

  /**
  * The Aroon indicator attempts to reveal information about a stocks trend
  * Aroon Oscillator = Aroon-Up - Aroon-Down where:
  * Aroon up = [(# of periods) - (# of periods since highest high)] / (# of periods)] x 100
  * Aroon down = [(# of periods) - (# of periods since lowest low)] / (# of periods)] x 100
  * When the oscillator is positive (often above 50), an upward trend bias is present; when the oscillator is negative (often below -50), a downward trend bias is present (range: -100 to 100)
  *
  * @param Array of $close data
  * @param Time $period to be used
  * @return Multidimensional array of calculated values. First is aroon up, second is aroon down, third is the aroon oscillator
  */
  protected function aroon($close, $period=25) {
    if (count($close) < $period + 15) {
      throw new Exception("Array \$close not long enough in call to aroon($close, $period)");
    }
    $aroon_up = array();
    $aroon_down = array();
    for ($i = $period; $i <= count($close); $i++) {
      $period_considered = array_slice($close, ($i - $period), $period, TRUE); // Preserve array keys
      $max = max($period_considered);
      $min = min($period_considered);
      $high_location = end(array_keys($period_considered, $max)) + 1;
      $low_location = end(array_keys($period_considered, $min)) + 1;
      $since_highest_high = abs($high_location - $i);
      $since_lowest_low = abs($low_location - $i);
      array_push($aroon_up, (($period - $since_highest_high) / $period) * 100);
      array_push($aroon_down, (($period - $since_lowest_low) / $period) * 100);
    }
    $aroon = array();
    for ($i = 0; $i < count($aroon_up); $i++) { 
      array_push($aroon, ($aroon_up[$i] - $aroon_down[$i]));
    }
    return array($aroon_up, $aroon_down, $aroon);
  }

  /**
  * Signals can be inferred from Aroon through
  * - Trend spotting
  *   There are three events which must occur for a new trend to take place
  *   (1) The aroon up/down cross eachother
  *   (2) The the aroon lines continue moving in opposite directions
  *   (3) One of the aroon lines then hits 100
  * - Consolidation periods
  *   If both aroon up/down are below 50 and dropped to that value at roughly the same time
  * - The value of the aroon oscillator
  *   If above 70, a strong uptrend is occuring; below -70, a strong downtrend is occuring. Warning: this oscillator has heavy lag
  */
  public function aroon_signal() {
    $close = $this->close;
    $aroon = $this->aroon($close);
    $aroon_up = $aroon[0];
    $aroon_down = $aroon[1];
    $aroon_osc = $aroon[2];
    // Trend spotting
    if (in_array(100, array_slice($aroon_up, -5, 5)) && (array_sum(array_slice($aroon_down, -5, 5)) / 5) < 50) { // Aroon up hit 100 while aroon down stayed low
      foreach (array_slice($aroon_osc, -20, 15) as $aroon_osc_point) { // If aroon up was below aroon down
        if ($aroon_osc_point < 0) {
          $this->rating += 5;
          array_push($this->notes, "Bullish Aroon trend found");
          break;
        }
      }
    }
    if (in_array(100, array_slice($aroon_down, -5, 5)) && (array_sum(array_slice($aroon_up, -5, 5)) / 5) < 50) {
      foreach (array_slice($aroon_osc, -20, 15) as $aroon_osc_point) {
        if ($aroon_osc_point > 0) {
          $this->rating -= 5;
          array_push($this->notes, "Bearish Aroon trend found");
          break;
        }
      }
    }
    // Consolidaton periods
    if (end(array_values($aroon_up)) < 50 && end(array_values($aroon_down)) < 50 && $this->pearson(array_slice($aroon_up, -10, 10)) < 0 && $this->pearson(array_slice($aroon_down, -10, 10)) < 0) {
      $this->rating = $this->rating / (20/15); // Bring a bit closer to 0
      array_push($this->notes, "Aroon found consolidation period; expect prices to not make large moves");
    }
    // The value of the aroon oscillator
    $last_aroon_osc = end(array_values($aroon_osc));
    if ($last_aroon_osc > 70) {
      $this->rating += $last_aroon_osc / 17;
      array_push($this->notes, "Aroon oscillator found strong uptrend (at $last_aroon_osc)");
    }
    elseif ($last_aroon_osc < -70) {
      $this->rating -= abs($last_aroon_osc) / 17;
      array_push($this->notes, "Aroon oscillator found strong downtrend (at $last_aroon_osc)");
    }
  }

  /**
  * The Parabolic SAR (stop and reverse) attempts to find entry/exit points by finding potential reversals in the price of a security
  * Current SAR = Prior SAR + Prior AF(Prior EP - Prior SAR) where:
  * Extreme point (EP) = The highest price point during an uptrend, or the lowest price point in a downtrend, and is updated if a new max or min is observed
  * Acceleration Factor (AF): Starting at .02, AF increases by .02 each time the extreme point makes a new high. AF can reach a maximum of .20, no matter how long the uptrend extends (for stocks 0.01 might be more appriopriate)
  *
  *
  * @param $high prices
  * @param $low prices
  * @param $close prices
  * @param Acceleration factor step $af_step
  * @param Max AF level $af_max
  * @return Parabolic SAR
  */
  protected function psar($high, $low, $close, $af_step=0.02, $af_max=0.2) {
    $af = array($af_step); // Acceleration factor
    $ep = array($low[0]); // Extreme point
    $psar = array($high[0]); // PSAR
    $trend = array(FALSE); // TRUE = uptrend/rising, FALSE = downtrend/falling
    $psar_ep_af = array(); // (PSAR - EP) * AF
    $initial_psar = array();
    for ($i = 0; $i < count($high); $i++) {
      // Calculate (PSAR - EP) * AF
      array_push($psar_ep_af, ($psar[$i] - $ep[$i]) * $af[$i]);
      // Calculate initial PSAR
      if ($trend[$i] === FALSE) {
        array_push($initial_psar, max(($psar[$i] - $psar_ep_af[$i]), $high[$i], $high[$i-1]));
      }
      else {
        array_push($initial_psar, min(($psar[$i] - $psar_ep_af[$i]), $low[$i], $low[$i - 1]));
      }
      // Calculate PSAR
      if (($trend[$i] === FALSE && $high[$i + 1] < $initial_psar[$i]) || ($trend[$i] === TRUE && $low[$i + 1] > $initial_psar[$i])) {
        array_push($psar, $initial_psar[$i]);
      }
      elseif (($trend[$i] === FALSE && $high[$i + 1] >= $initial_psar[$i]) || ($trend[$i] === TRUE && $low[$i + 1] <= $initial_psar[$i])) {
        array_push($psar, $ep[$i]);
      }
      // Calculate change in trend
      if ($psar[$i + 1] > $close[$i + 1]) {
        array_push($trend, FALSE);
      }
      else {
        array_push($trend, TRUE);
      }
      // Calculate EP
      if ($trend[$i + 1] === FALSE) {
        array_push($ep, min($ep[$i], $low[$i + 1]));
      }
      else {
        array_push($ep, max($ep[$i], $high[$i + 1]));
      }
      // Calculate change in AF
      if ($trend[$i] === $trend[$i + 1] && $ep[$i] != $ep[$i + 1] && $af[$i] < ($af_max - 0.01)) {
        array_push($af, end(array_values($af)) + $af_step);
      }
      elseif ($psar_ep_af[$i] == $psar_ep_af[$i + 1] && $ep[$i] == $ep[$i + 1]) {
        array_push($af, end(array_values($af)));
      }
      elseif ($trend[$i] !== $trend[$i + 1]) {
        array_push($af, $af_step);
      }
    }
    array_pop($psar);
    return $psar;
  }

  /**
  * The Average Directional Index (ADX) tells whether a security is trending or oscillating
  * The ADX is a combination of the positive directional indicator (+DI) and negative directional indicator (-DI)
  *
  * @param $high prices
  * @param $low prices
  * @param $close prices
  * @return Multidimensional array of calculated ADX values (first array is the +DI, second is the -DI, third is the ADX)
  */
  protected function adx($high, $low, $close) {
    $pdm = array();
    $ndm = array();
    for ($i = 1; $i < count($high); $i++) { 
      $up_move = $high[$i] - $high[$i - 1];
      $down_move = $low[$i - 1] - $low[$i];
      if ($up_move > $down_move && $up_move > 0) {
        array_push($pdm, $up_move);
      }
      else {
        array_push($pdm, 0);
      }
      if ($down_move > $up_move && $down_move > 0) {
        array_push($ndm, $down_move);
      }
      else {
        array_push($ndm, 0);
      }
    }
    $tr = $this->atr($high, $low, $close)[0];
    array_shift($tr);
    $tr14 = array(array_sum(array_slice($tr, 0, 14)));
    for ($i = 14; $i < count($tr); $i++) {
      array_push($tr14, $tr14[$i - 14] - ($tr14[$i - 14] / 14) + $tr[$i]);
    }
    $pdm14 = array(array_sum(array_slice($pdm, 0, 14)));
    $ndm14 = array(array_sum(array_slice($ndm, 0, 14)));
    for ($i = 14; $i < count($pdm); $i++) {
      array_push($pdm14, $pdm14[$i - 14] - ($pdm14[$i - 14] / 14) + $pdm[$i]);
      array_push($ndm14, $ndm14[$i - 14] - ($ndm14[$i - 14] / 14) + $ndm[$i]);
    }
    $pdi14 = array();
    $ndi14 = array();
    $di14diff = array();
    $di14sum = array();
    $dx = array();
    for ($i = 0; $i < count($high) - 14; $i++) {
      array_push($pdi14, 100 * ($pdm14[$i] / $tr14[$i]));
      array_push($ndi14, 100 * ($ndm14[$i] / $tr14[$i]));
      array_push($di14diff, abs($pdi14[$i] - $ndi14[$i]));
      array_push($di14sum, $pdi14[$i] + $ndi14[$i]);
      array_push($dx, 100 * ($di14diff[$i] / $di14sum[$i]));
    }
    $adx = array(array_sum(array_slice($dx, 0, 14)) / 14);
    for ($i = 14; $i < count($dx); $i++) {
      array_push($adx, (($adx[$i - 14] * 13) + $dx[$i]) / 14);
    }
    return array($pdi14, $ndi14, $adx);
  }

  /**
  * Parabolic SAR & ADX
  * To avoid whipsaws brought on by false PSAR signals, the PSAR is used in conjunction with the ADX
  * - Sell if the +DI line is below the -DI line and PSAR gives sell signal (PSAR above price)
  * - Buy if the +DI line is above the -DI line and PSAR gives buy signal (PSAR below price)
  */
  public function psar_adx_signal() {
    $high = $this->high;
    $low = $this->low;
    $close = $this->close;
    $psar = $this->psar($high, $low, $close);
    $adx = $this->adx($high, $low, $close);
    $pdi = $adx[0];
    $ndi = $adx[1];
    if (end(array_values($pdi)) > end(array_values($ndi)) && end(array_values($close)) > end(array_values($psar))) {
      $this->rating -= 10;
      array_push($this->notes, "Bearish Parabolic SAR/ADX signal (+DI above -DI and PSAR below price)");
    }
    elseif (end(array_values($pdi)) < end(array_values($ndi)) && end(array_values($psar)) > end(array_values($close))) {
      $this->rating += 10;
      array_push($this->notes, "Bullish Parabolic SAR/ADX signal (+DI below -DI and PSAR above price)");
    }
  }

  /**
  * The Accumulation Distribution (A/D) line measures to flow of money in to/out of a security i.e. the supply/demand of the security.
  * The calculation takes places in three steps
  * 1. Calculate the Money Flow Multiplier = [(Close  -  Low) - (High - Close)] /(High - Low)
  * 2. Calculate the Money Flow Volume = Money Flow Multiplier x Volume for the Period
  * 3. Calculate the A/D line = Previous A/D line + Current Period's Money Flow Volume
  * All of these steps have been condensed to speed up the algo
  * Because of its similiarity to Chaikin Money Flow (CMF), the CMF has been omitted
  *
  * @param $high prices
  * @param $low prices
  * @param $close prices
  * @param $volume
  * @return Array of calculated A/D values
  */
  protected function ad($high, $low, $close, $volume) {
    $ad = array();
    for ($i = 0; $i < count($high); $i++) {
      $mf = ((($close[$i] - $low[$i]) - ($high[$i] - $close[$i])) / ($high[$i] - $low[$i]) * $volume[$i]);
      if ($i === 0) {
        array_push($ad, $mf);
      }
      else {
        array_push($ad, $ad[$i - 1] + $mf);
      }
    }
    return $ad;
  }

  /**
  * Accumulation Distribution
  * Signals can be inferred from the A/D line through divergences
  */
  public function ad_signal() {
    $high = $this->high;
    $low = $this->low;
    $close = $this->close;
    $volume = $this->volume;
    // A bullish divergence occurs when price trends downward and the A/D line trends up; a bearish divergence occurs when price trends upward and the A/D line trends down
    $ad = $this->ad($high, $low, $close, $volume);
    if ($this->is_divergence(array_slice($ad, -7), array_slice($close, -7), 0.4)) {
      $this->rating += 10;
      array_push($this->notes, "Bullish A/D divergence found. Expect price to rise sometime in the (possibly distant) future");
    }
    elseif ($this->is_divergence(array_slice($close, -7), array_slice($ad, -7), 0.4)) {
      $this->rating -= 10;
      array_push($this->notes, "Bearish A/D divergence found. Expect price to fall sometime in the (possibly distant) future");
    }
  }

  /**
  * Bollinger Bands (created by John Bollinger in the early 1980s) are a way to measure volatility and identify overbought/oversold levels
  *
  * @param $close prices
  * @return Multidimensional array of calculated values (first array is the lower band, second is the middle band, third the upper band)
  */
  protected function bb($close) {
    $middle_band = $this->sma($close, 20);
    $upper_band = array();
    $lower_band = array();
    for ($i = 0; $i < count($middle_band); $i++) { 
      array_push($upper_band, $middle_band[$i] + ($this->sd(array_slice($close, $i, 20)) * 2));
      array_push($lower_band, $middle_band[$i] - ($this->sd(array_slice($close, $i, 20)) * 2));
    }
    return array($lower_band, $middle_band, $upper_band);
  }

  /**
  * Bollinger Bands
  * Although uses for Bollinger Bands such as identifying volatility and W-tops/M-bottoms is common, perhaps the most useful signal BB provides is when a security is overbought/oversold because price will move outside the easily identifiable lower/upper band
  * A bearish signal is when price moves above the upper band
  * A bullish signal is when price moves below the lower band
  */
  public function bb_signal() {
    $close = $this->close;
    $bb = $this->bb($close);
    $lower_band = $bb[0];
    $upper_band = $bb[2];
    $curr_close = end(array_values($close));
    $curr_lower_band = end(array_values($lower_band));
    $curr_upper_band = end(array_values($upper_band));
    if ($curr_close < $curr_lower_band) {
      $this->rating += 20;
      array_push($this->notes, "Price moved below lower Bollinger Band");
    }
    elseif ($curr_close > $curr_upper_band) {
      $this->rating -= 20;
      array_push($this->notes, "Price moved above upper Bollinger Band");
    }
  }

  /**
  * Commodity Channel Index (CCI) is used to identify overbough/oversold levels
  *
  * @param $high prices
  * @param $low prices
  * @param $close prices
  * @return Array of calculated CCI values
  */
  protected function cci($high, $low, $close) {
    $typical_price = array();
    for ($i = 0; $i < count($high); $i++) { 
      array_push($typical_price, ($high[$i] + $low[$i] + $close[$i]) / 3);
    }
    $tp_sma = $this->sma($typical_price, 20);
    $mean_deviation = array();
    for ($i = 0; $i < count($tp_sma); $i++) {
      $x = 0;
      for ($j = $i; $j < 20 + $i; $j++) { 
        $x += abs($tp_sma[$i] - $typical_price[$j]);
      }
      array_push($mean_deviation, $x / 20);
    }
    $cci = array();
    for ($i = 0; $i < count($mean_deviation); $i++) { 
      array_push($cci, ($typical_price[$i + 19] - $tp_sma[$i]) / ($mean_deviation[$i] * 0.015));
    }
    return $cci;
  }

  /**
  * Commodity Channel Index
  * CCIs primary function is to identify overbought/oversold levels
  * A security is thought to be oversold below -100 and overbought above 100
  */
  public function cci_signal() {
    $cci = $this->cci($this->high, $this->low, $this->close);
    $curr_cci = end($cci);
    if ($curr_cci < -100) {
      $this->rating += 10; // Security is oversold; add to signal
      array_push($this->notes, "Bullish CCI signal ($curr_cci)");
    }
    elseif ($curr_cci > 100) {
      $this->rating -= 10; // Security is overbought; subtract from signal
      array_push($this->notes, "Bearish CCI signal ($curr_cci)");
    }
  }

  /**
  * The Money Flow Index (MFI) considers price and volume to identify buying/selling pressures
  * The calculation has four steps
  * 1. Calculate the typical price ((High + Low + Close) / 3)
  * 2. Calculate the Raw Money Flow (Typical Price * Volume)
  * 3. Calculate the Money Flow Ratio ((14 Period Positive Money Flow) / (14 Period Negative Money Flow))
  * 4. 100 - 100/(1 + Money Flow Ratio) = Money Flow Index)
  *
  * @param $high prices
  * @param $low prices
  * @param $close prices
  * @param $volume
  * @return Array of calculated values
  */
  protected function mfi($high, $low, $close, $volume) {
    $typical_price = array();
    $rmf = array();
    for ($i = 0; $i < count($high); $i++) { 
      $x = ($high[$i] + $low[$i] + $close[$i]) / 3;
      array_push($typical_price, $x);
      array_push($rmf, $x * $volume[$i]);
    }
    array_shift($rmf);
    $pmf = array();
    $nmf = array();
    for ($i = 0; $i < count($rmf); $i++) { 
      if ($typical_price[$i + 1] > $typical_price[$i]) {
        array_push($pmf, $rmf[$i]);
        array_push($nmf, NULL);
      }
      else {
        array_push($pmf, NULL);
        array_push($nmf, $rmf[$i]);
      }
    }
    $mfi = array();
    for ($i = 0; $i <= (count($pmf) - 14); ++$i) {
      $mfr14 = array_sum(array_slice($pmf, $i, 14)) / array_sum(array_slice($nmf, $i, 14));
      array_push($mfi, 100 - (100 / (1 + $mfr14)));
    }
    return $mfi;
  }

  /**
  * Money Flow Index
  * Signals can be inferred from the MFI through
  * - Overbought/oversold levels
  *   Above 80 is considered overbought, and below 20 is oversold
  * - Divergences
  *   Bullish divergence is when price makes a new low but MFI makes a higher low, bearish is when MFI makes new low but price makes a higher low
  */
  public function mfi_signal() {
    $mfi = $this->mfi($this->high, $this->low, $this->close, $this->volume);
    $curr_mfi = end(array_values($mfi));
    // Overbought/oversold levels
    if ($curr_mfi > 80) {
      $this->rating -= pow(($curr_mfi - 80) / 15, 2) + 5; // Security is overbought; subtract from signal based on magnitude of MFI
      array_push($this->notes, "Bearish MFI reading (MFI at $curr_mfi)");
    }
    if ($curr_mfi < 20) {
      $this->rating += pow((((-$curr_mfi) + 20) / 15), 2) + 5; // Security is oversold; add to signal based on magnitude of MFI
      array_push($this->notes, "Bullish MFI reading (MFI at $curr_mfi)");
    }
    // Divergences
    if ($this->is_divergence(array_slice($mfi, -10), array_slice($this->close, -10), 0.4)) {
      $this->rating += 5;
      array_push($this->notes, "Bullish MFI divergence found");
    }
    elseif ($this->is_divergence(array_slice($this->close, -10), array_slice($mfi, -10), 0.4)) {
      $this->rating -= 5;
      array_push($this->notes, "Bearish MFI divergence found");
    }
  }

  /**
  * On balance volume (OBV) relates price and volume to measure buy/sell presures
  *
  * @param $close prices
  * @param $volume
  * @return Array of calculated OBV values
  */
  protected function obv($close, $volume) {
    $obv = array($volume[0]);
    for ($i = 1; $i < count($close); $i++) { 
      if ($close[$i] > $close[$i - 1]) {
        array_push($obv, $obv[$i - 1] + $volume[$i]);
      }
      elseif ($close[$i] < $close[$i - 1]) {
        array_push($obv, $obv[$i - 1] - $volume[$i]);
      }
      else {
        array_push($obv, $obv[$i - 1]);
      }
    }
    return $obv;
  }

  /**
  * On balance volume
  * Divergences are the primary too to identify signals with OBV
  * Bullish divergences are where price trends down and OBV trends up
  * Bearish divergences are where price trends up and OBV trends down
  */
  public function obv_signal() {
    $close = $this->close;
    $volume = $this->volume;
    $obv = $this->obv($close, $volume);
    if ($this->is_divergence(array_slice($obv, -15), array_slice($close, -15), 0.4)) {
      $this->rating += 10;
      array_push($this->notes, "Bullish OBV divergence found");
    }
    elseif ($this->is_divergence(array_slice($close, -15), array_slice($obv, -15), 0.4)) {
      $this->rating -= 10;
      array_push($this->notes, "Bearish OBV divergence found");
    }
  }

  /**
  * The Rate of Change (ROC) is a "pure" momentum oscillator and can be used to identify trends and overbought/oversold levels
  * Warning: this indicator produces many false signals when trying to use divergences to infer buy/sell points. Additionally, whipsaws are common in the short range. Use instead to identify overbought/oversold levels
  * ROC = [(Close - Close n periods ago) / (Close n periods ago)] * 100
  *
  * @param $prices
  * @param $n period
  * @return Array of calculated ROC values
  */
  protected function roc($prices, $n=12) {
    $roc = array();
    for ($i = 0; $i < (count($prices) - $n); ++$i) {
      array_push($roc, ($prices[$i + $n] - $prices[$i]) / $prices[$i] * 100);
    }
    return $roc;
  }

  /**
  * Rate of Change
  * Aside from confirming trends in price, ROC can be used to identify overbought/oversold levels
  * Unlike most oscillators, ROC is not range bound which makes identificaiton more complex
  * This algorithim uses -10% for oversold, 10% for overbought
  */
  public function roc_signal() {
    $roc = $this->roc($this->close);
    $trend = array_sum(array_slice($roc, -30)) / count($roc);
    // If general trend is bullish, find instances where ROC crosses the -10% oversold threshold
    if ($trend > 1 && end(array_values($roc)) < -10) {
      $this->rating += 50;
      array_push($this->notes, "ROC identified oversold level");
    }
    // If general trend is bearish, find instances where ROC crosses the 10% overbought threshold
    elseif ($trend < -1 && end(array_values($roc)) > 10) {
      $this->rating -= 50;
      array_push($this->notes, "ROC identified overbought level");
    }
  }

  /**
  * Three Black Crows candlestick pattern
  * See more: https://en.wikipedia.org/wiki/Three_Black_Crows
  *
  * @return true if pattern is present, false if it is not
  */
  protected function is3black_crows() {
    $open = array_slice($this->open, -3);
    $close = array_slice($this->close, -3);
    $x = $close[0] * 0.003; // min height of each candle
    // Checks if each open price is lower than the previous AND each close prices is lower than the previous AND each candlestick has a not insignifigant height
    if (($open[0] > $open[1] && $open[1] > $open[2]) && ($close[0] > $close[1] && $close[1] > $close[2]) && ($open[0] - $close[0] > $x && $open[1] - $close[1] > $x && $open[2] - $close[2] > $x)) {
      return TRUE;
    }
    return FALSE;
  }

  /**
  * Three white soldiers candlestick pattern (Opposite of three black crows)
  * See more: https://en.wikipedia.org/wiki/Three_white_soldiers
  *
  * @return true if pattern is present, false if it is not
  */
  protected function is3white_soldiers() {
    $open = array_slice($this->open, -3);
    $close = array_slice($this->close, -3);
    $x = $close[0] * 0.003; // min height of each candle
    // Checks if each open price is high than the previous AND each close prices is higher than the previous AND each candlestick has a not insignifigant height
    if (($open[0] < $open[1] && $open[1] < $open[2]) && ($close[0] < $close[1] && $close[1] < $close[2]) && ($close[0] - $open[0] > $x && $close[1] - $open[1] > $x && $close[2] - $open[2] > $x)) {
      return TRUE;
    }
    return FALSE;
  }

  /**
  * As previously stated, a negative signal indicates the security is overbought (and should be sold) while a positive signal indicates the security is underbought (and should be bought)
  */
  public function signal($all=FALSE) {
    if ($all) {
      $this->macd_signal();
      $this->stoch_signal();
      $this->rsi_signal();
      $this->aroon_signal();
      $this->psar_adx_signal();
      $this->ad_signal();
      $this->bb_signal();
      $this->cci_signal();
      $this->mfi_signal();
      $this->obv_signal();
      $this->roc_signal();
    }
    else {
      $this->rsi_signal();
      $this->bb_signal();
      $this->roc_signal();
    }
  }

}

?>