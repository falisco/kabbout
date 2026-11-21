// What the bridge may refuse by itself, before the engine is asked anything.

/** The request is not one the bridge understands: wrong shape, missing field, too large. Answered 400 (or `status`). */
export class BadRequest extends Error {
  status: number;
  constructor(message: string, status = 400) {
    super(message);
    this.status = status;
  }
}
