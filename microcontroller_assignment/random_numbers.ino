/*******************************************************
 * Title: Random_Numbers_ESP8266
 * -----------------------------------------------------
 * Program Detail:
 *   Purpose: Generate random integers in [0..100] every 5 seconds
 *            and print them to Serial for visualization in the
 *            Arduino Serial Plotter (one point per 5 s).
 *   Inputs:  None (hardware RNG used internally)
 *   Outputs: Serial (115200 baud) → integers 0..100
 *   Date:    <fill in date/time>
 *   Compiler: Arduino IDE (ESP8266 core)
 *   Author:  Alexander Pagaduan
 *   Versions:
 *     V1 – Initial version: unbiased RNG mapping + 5 s interval
 *
 * -----------------------------------------------------
 * File Dependencies: listing and header files needed to run
 *   - Arduino.h
 *   - user_interface.h  (ESP8266 SDK; provides os_random())
 *******************************************************/

 // ================================
 // Place all the libraries/dependencies here
 // ================================
#include <Arduino.h>
extern "C" {
  #include "user_interface.h"   // ESP8266 SDK header for os_random()
}

// ================================
// Main Program
// ================================

// 32-bit hardware RNG wrapper (ESP8266)
static inline uint32_t hwRand() { return os_random(); }

// Uniform [0, n) without modulo bias (rejection sampling)
uint32_t randRange(uint32_t n) {
  if (n == 0) return 0;
  const uint32_t limit = (UINT32_MAX / n) * n;  // largest multiple of n <= UINT32_MAX
  uint32_t r;
  do { r = hwRand(); } while (r >= limit);
  return r % n;
}

void setup() {
  Serial.begin(115200);
  delay(2000);            // small delay so Serial Monitor/Plotter can attach
  // NOTE: Do NOT print any text here; Serial Plotter expects numbers only.
}

void loop() {
  // Print a single integer in [0..100] every 5 seconds.
  // (Keep it as a bare number so Serial Plotter graphs it.)
  Serial.println(randRange(101));
  delay(5000);
}
